<?php

namespace App\Services\Analytics;

use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\PageView;
use App\Models\Player;
use App\Services\Settings\SettingsService;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the page_views analytics (see PageViewRecorder for how rows
 * are captured). Everything is a SQL aggregate over the two display-timezone
 * columns the recorder writes — local_date and local_hour — so there are no
 * database-specific date functions (tests run on SQLite, production on
 * MySQL) and no raw rows are loaded into PHP. Visitors are only ever
 * counted (COUNT DISTINCT visitor_hash); the hash itself is never selected
 * for display.
 */
class AdminAnalyticsService
{
    public const PRESETS = ['today', 'yesterday', 'last7', 'last30', 'custom'];

    /**
     * Content-type filter value => page_views.event_type (null = all).
     * An allow-list: request input never reaches a query unmapped.
     */
    public const TYPES = [
        'all' => null,
        'match' => PageView::MATCH_VIEW,
        'edition' => PageView::EDITION_VIEW,
        'player' => PageView::PLAYER_VIEW,
    ];

    public const MAX_RANGE_DAYS = 366;

    public const TOP_PER_PAGE = 10;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * The timezone every analytics date and hour is expressed in.
     */
    public function timezone(): string
    {
        return (string) $this->settings->get('system.display_timezone');
    }

    /**
     * The preset ranges are calendar dates in the configured display
     * timezone — the same timezone the recorder used for local_date — and
     * include today for "last N days".
     *
     * @return array{from: string, to: string}
     */
    public function resolveRange(string $preset, ?string $from = null, ?string $to = null): array
    {
        $today = Carbon::now($this->settings->get('system.display_timezone'))->startOfDay();

        return match ($preset) {
            'today' => ['from' => $today->toDateString(), 'to' => $today->toDateString()],
            'yesterday' => ['from' => $today->copy()->subDay()->toDateString(), 'to' => $today->copy()->subDay()->toDateString()],
            'last30' => ['from' => $today->copy()->subDays(29)->toDateString(), 'to' => $today->toDateString()],
            'custom' => ['from' => (string) $from, 'to' => (string) $to],
            default => ['from' => $today->copy()->subDays(6)->toDateString(), 'to' => $today->toDateString()],
        };
    }

    /**
     * Total views and unique visitors for the range, narrowed to one event
     * type when $eventType is given.
     *
     * @return array{total: int, visitors: int}
     */
    public function summary(string $from, string $to, ?string $eventType): array
    {
        $row = $this->base($from, $to, $eventType)
            ->selectRaw('count(*) as total, count(distinct visitor_hash) as visitors')
            ->first();

        return ['total' => (int) ($row->total ?? 0), 'visitors' => (int) ($row->visitors ?? 0)];
    }

    /**
     * Views and unique visitors per event type for the range, always for
     * every type (it is the "where is the traffic going" overview, so the
     * content-type filter deliberately does not narrow it).
     *
     * @return array<string, array{views: int, visitors: int}>
     */
    public function breakdown(string $from, string $to): array
    {
        $rows = $this->base($from, $to, null)
            ->selectRaw('event_type, count(*) as views, count(distinct visitor_hash) as visitors')
            ->groupBy('event_type')
            ->get()
            ->keyBy('event_type');

        $result = [];

        foreach (PageView::EVENT_TYPES as $type) {
            $result[$type] = [
                'views' => (int) ($rows[$type]->views ?? 0),
                'visitors' => (int) ($rows[$type]->visitors ?? 0),
            ];
        }

        return $result;
    }

    /**
     * One row per calendar day in the range (days without views are
     * included as zeros), oldest first.
     *
     * @return list<array{date: string, views: int, visitors: int}>
     */
    public function daily(string $from, string $to, ?string $eventType): array
    {
        $rows = $this->base($from, $to, $eventType)
            ->selectRaw('local_date, count(*) as views, count(distinct visitor_hash) as visitors')
            ->groupBy('local_date')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->local_date, 0, 10));

        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();
            $days[] = [
                'date' => $date,
                'views' => (int) ($rows[$date]->views ?? 0),
                'visitors' => (int) ($rows[$date]->visitors ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Views per local hour of day (0-23) across the whole range, zeros
     * included.
     *
     * @return list<array{hour: int, views: int}>
     */
    public function hourly(string $from, string $to, ?string $eventType): array
    {
        $rows = $this->base($from, $to, $eventType)
            ->selectRaw('local_hour, count(*) as views')
            ->groupBy('local_hour')
            ->pluck('views', 'local_hour');

        return array_map(
            fn (int $hour) => ['hour' => $hour, 'views' => (int) ($rows[$hour] ?? 0)],
            range(0, 23),
        );
    }

    /**
     * Most-viewed subjects of one event type, paginated, with their names
     * loaded in a single extra query. A subject that no longer exists (its
     * page_views rows are kept on purpose) is returned as a "Deleted …"
     * row rather than dropped or failing.
     *
     * @return LengthAwarePaginator<int, array{subject_id: int, views: int, visitors: int, label: string, detail: ?string, url: ?string, deleted: bool}>
     */
    public function top(string $eventType, string $from, string $to, string $pageName): LengthAwarePaginator
    {
        $paginator = $this->base($from, $to, $eventType)
            ->select('subject_id')
            ->selectRaw('count(*) as views')
            ->selectRaw('count(distinct visitor_hash) as visitors')
            ->groupBy('subject_id')
            ->orderByDesc('views')
            ->orderBy('subject_id')
            ->paginate(self::TOP_PER_PAGE, ['*'], $pageName);

        $subjects = $this->loadSubjects($eventType, $paginator->getCollection()->pluck('subject_id')->all());

        return $paginator->through(function ($row) use ($eventType, $subjects) {
            $id = (int) $row->subject_id;
            $described = $subjects[$id] ?? [
                'label' => __('Deleted :type #:id', ['type' => $this->subjectNoun($eventType), 'id' => $id]),
                'detail' => null,
                'url' => null,
                'deleted' => true,
            ];

            return ['subject_id' => $id, 'views' => (int) $row->views, 'visitors' => (int) $row->visitors] + $described;
        });
    }

    private function base(string $from, string $to, ?string $eventType): Builder
    {
        return DB::table('page_views')
            ->whereBetween('local_date', [$from, $to])
            ->when($eventType, fn (Builder $query, string $type) => $query->where('event_type', $type));
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int, array{label: string, detail: ?string, url: ?string, deleted: bool}>
     */
    private function loadSubjects(string $eventType, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $described = [];

        switch ($eventType) {
            case PageView::MATCH_VIEW:
                $matches = GameMatch::query()->with(['teamA.team', 'teamB.team', 'edition'])->whereIn('id', $ids)->get();

                foreach ($matches as $match) {
                    $teamA = $match->teamA->team->short_name ?: $match->teamA->team->name;
                    $teamB = $match->teamB->team->short_name ?: $match->teamB->team->name;

                    $described[$match->id] = [
                        'label' => "{$teamA} vs {$teamB}",
                        'detail' => __('Match :number', ['number' => $match->match_number]).' · '.$match->edition->name,
                        'url' => route('admin.matches.show', $match),
                        'deleted' => false,
                    ];
                }
                break;

            case PageView::EDITION_VIEW:
                foreach (Edition::query()->whereIn('id', $ids)->get() as $edition) {
                    $described[$edition->id] = [
                        'label' => $edition->name,
                        'detail' => (string) $edition->year,
                        'url' => route('admin.editions.show', $edition),
                        'deleted' => false,
                    ];
                }
                break;

            case PageView::PLAYER_VIEW:
                foreach (Player::query()->select(['id', 'name'])->whereIn('id', $ids)->get() as $player) {
                    $described[$player->id] = [
                        'label' => $player->name,
                        'detail' => null,
                        'url' => route('admin.players.show', $player),
                        'deleted' => false,
                    ];
                }
                break;
        }

        return $described;
    }

    private function subjectNoun(string $eventType): string
    {
        return match ($eventType) {
            PageView::MATCH_VIEW => __('Match'),
            PageView::EDITION_VIEW => __('Edition'),
            default => __('Player'),
        };
    }
}
