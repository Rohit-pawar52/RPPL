<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameMatch\StoreSeasonMatchRequest;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\Venue;
use App\Services\GameMatch\GameMatchService;
use App\Services\Settings\DisplayTimezoneFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The matches of ONE season, managed from inside that season (the Edition
 * hub's "Matches" card): this season's fixtures in one compact list, and
 * scheduling a new one with the season fixed and only its own teams on offer.
 * The standalone Matches pages (and everything match-day: toss, scoring, ...)
 * are unchanged; both create matches through the same GameMatchService.
 */
class SeasonMatchController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly GameMatchService $matches,
        private readonly DisplayTimezoneFormatter $displayTimezone,
    ) {}

    public function index(Request $request, Edition $edition): View
    {
        $this->authorize('viewAny', GameMatch::class);

        $status = $request->query('match_status');
        $status = in_array($status, GameMatch::STATUSES, true) ? $status : null;

        $matches = GameMatch::query()
            ->where('edition_id', $edition->id)
            ->when($status, fn ($query) => $query->where('match_status', $status))
            ->with(['teamA.team', 'teamB.team', 'venue'])
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.editions.matches.index', [
            'edition' => $edition,
            'matches' => $matches,
            'status' => $status,
            'canAdd' => $edition->status !== 'completed',
        ]);
    }

    public function create(Edition $edition): View|RedirectResponse
    {
        $this->authorize('create', GameMatch::class);

        if ($blocked = $this->completedBlock($edition)) {
            return $blocked;
        }

        $latest = GameMatch::where('edition_id', $edition->id)->latest('id')->first();

        return view('admin.editions.matches.create', [
            'edition' => $edition,
            'editionTeams' => $this->seasonTeams($edition),
            'venues' => Venue::active()->orderBy('name')->get(),
            'stages' => GameMatch::STAGES,
            // Same starting values as the standalone form, for this season.
            'defaults' => [
                'match_number' => (int) GameMatch::where('edition_id', $edition->id)->max('match_number') + 1,
                'overs_per_innings' => $latest?->overs_per_innings ?? 20,
                'venue_id' => Venue::defaultId() ?? $latest?->venue_id,
            ],
        ]);
    }

    public function store(StoreSeasonMatchRequest $request, Edition $edition): RedirectResponse
    {
        $this->authorize('create', GameMatch::class);

        if ($blocked = $this->completedBlock($edition)) {
            return $blocked;
        }

        $data = $request->validated();
        $data['scheduled_at'] = $this->displayTimezone->parseFromDisplayTimezone($data['scheduled_at']);
        // An unchecked checkbox is simply absent from the request.
        $data['reminder_enabled'] = $request->boolean('reminder_enabled');

        $this->matches->createMatch($data);

        return redirect()
            ->route('admin.editions.matches.index', $edition)
            ->with('success', 'Match created successfully.');
    }

    /**
     * This season's teams that can be scheduled (the team itself must be active).
     */
    private function seasonTeams(Edition $edition)
    {
        return $edition->editionTeams()
            ->whereHas('team', fn ($query) => $query->where('is_active', true))
            ->with('team')
            ->get()
            ->sortBy(fn ($editionTeam) => mb_strtolower($editionTeam->team->name))
            ->values();
    }

    private function completedBlock(Edition $edition): ?RedirectResponse
    {
        if ($edition->status !== 'completed') {
            return null;
        }

        return redirect()
            ->route('admin.editions.matches.index', $edition)
            ->with('error', 'This season is completed, so new matches cannot be scheduled.');
    }
}
