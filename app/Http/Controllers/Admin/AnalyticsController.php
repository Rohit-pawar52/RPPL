<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Models\User;
use App\Services\Analytics\AdminAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only admin view of the public page-view analytics. Needs the
 * analytics.view permission (independent of reports.view); nothing here
 * writes anything.
 *
 * The filters are validated from the query string, but a bad value renders
 * the page with the default range plus the error messages — it never
 * redirects away, so a hand-edited or stale URL doesn't dump the admin on
 * another page.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AdminAnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        Gate::allowIf(fn (User $user) => $user->hasPermission('analytics.view'));

        $validator = Validator::make($request->query(), [
            'range' => ['nullable', Rule::in(AdminAnalyticsService::PRESETS)],
            'type' => ['nullable', Rule::in(array_keys(AdminAnalyticsService::TYPES))],
            'from_date' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($validator->errors()->isNotEmpty() || $request->query('range') !== 'custom') {
                return;
            }

            $days = Carbon::parse($request->query('from_date'))->diffInDays(Carbon::parse($request->query('to_date'))) + 1;

            if ($days > AdminAnalyticsService::MAX_RANGE_DAYS) {
                $validator->errors()->add('to_date', __('Choose a range of at most :days days.', ['days' => AdminAnalyticsService::MAX_RANGE_DAYS]));
            }
        });

        $valid = ! $validator->fails();

        $range = $valid ? ($request->query('range') ?: 'last7') : 'last7';
        $type = $valid ? ($request->query('type') ?: 'all') : 'all';
        $eventType = AdminAnalyticsService::TYPES[$type];

        $dates = $this->analytics->resolveRange(
            $range,
            $valid ? $request->query('from_date') : null,
            $valid ? $request->query('to_date') : null,
        );

        return view('admin.analytics.index', [
            // What the report is actually using.
            'filters' => [
                'range' => $range,
                'type' => $type,
            ],
            // What the form shows. When the request was invalid the report
            // falls back to the defaults, but the form keeps what the admin
            // typed (as plain strings only) so they can correct it.
            'form' => $this->formValues($request, $valid, $range, $type),
            'dates' => $dates,
            'timezone' => $this->analytics->timezone(),
            'summary' => $this->analytics->summary($dates['from'], $dates['to'], $eventType),
            'breakdown' => $this->analytics->breakdown($dates['from'], $dates['to']),
            'daily' => $this->analytics->daily($dates['from'], $dates['to'], $eventType),
            'hourly' => $this->analytics->hourly($dates['from'], $dates['to'], $eventType),
            'topMatches' => $this->topFor($type, 'match', PageView::MATCH_VIEW, 'matches_page', $dates),
            'topEditions' => $this->topFor($type, 'edition', PageView::EDITION_VIEW, 'editions_page', $dates),
            'topPlayers' => $this->topFor($type, 'player', PageView::PLAYER_VIEW, 'players_page', $dates),
        ])->withErrors($validator);
    }

    /**
     * @return array{range: string, type: string, from_date: ?string, to_date: ?string}
     */
    private function formValues(Request $request, bool $valid, string $range, string $type): array
    {
        $requestedRange = $request->query('range');
        $requestedType = $request->query('type');

        return [
            'range' => $valid ? $range : (is_string($requestedRange) && in_array($requestedRange, AdminAnalyticsService::PRESETS, true) ? $requestedRange : $range),
            'type' => $valid ? $type : (is_string($requestedType) && array_key_exists($requestedType, AdminAnalyticsService::TYPES) ? $requestedType : $type),
            'from_date' => $this->plainString($request->query('from_date')),
            'to_date' => $this->plainString($request->query('to_date')),
        ];
    }

    /**
     * Query values can be arrays (?from_date[]=x); only a short plain string
     * is ever echoed back into the form.
     */
    private function plainString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 32) : null;
    }

    /**
     * A top-content table is only built when the content-type filter is "all"
     * or that table's own type.
     *
     * @param  array{from: string, to: string}  $dates
     */
    private function topFor(string $selectedType, string $tableType, string $eventType, string $pageName, array $dates)
    {
        if ($selectedType !== 'all' && $selectedType !== $tableType) {
            return null;
        }

        return $this->analytics->top($eventType, $dates['from'], $dates['to'], $pageName)->withQueryString();
    }
}
