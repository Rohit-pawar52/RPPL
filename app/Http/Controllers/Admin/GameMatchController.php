<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameMatch\StoreGameMatchRequest;
use App\Http\Requests\Admin\GameMatch\UpdateGameMatchRequest;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Venue;
use App\Services\GameMatch\GameMatchService;
use App\Services\GameMatch\MatchFlowService;
use App\Services\GameMatch\MatchResultService;
use App\Services\Innings\InningsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameMatchController extends Controller
{
    public function __construct(
        private readonly GameMatchService $matches,
        private readonly MatchFlowService $matchFlow,
        private readonly InningsService $innings,
        private readonly MatchResultService $results,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GameMatch::class);

        $filters = $request->only(['search', 'edition_id', 'match_status']);

        $matches = GameMatch::query()
            ->with(['edition', 'teamA.team', 'teamB.team', 'venue'])
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->whereHas('teamA.team', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('teamB.team', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('venue', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                })
            )
            ->when(
                $filters['edition_id'] ?? null,
                fn ($query, $editionId) => $query->where('edition_id', $editionId)
            )
            ->when(
                in_array($filters['match_status'] ?? null, GameMatch::STATUSES, true),
                fn ($query) => $query->where('match_status', $filters['match_status'])
            )
            ->orderByDesc('scheduled_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.matches.index', [
            'matches' => $matches,
            'filters' => $filters,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GameMatch::class);

        return view('admin.matches.create', [
            'editions' => Edition::openForParticipation()->orderByDesc('year')->get(),
            'editionTeams' => $this->eligibleEditionTeams(),
            'venues' => Venue::active()->orderBy('name')->get(),
            'stages' => GameMatch::STAGES,
        ]);
    }

    public function store(StoreGameMatchRequest $request): RedirectResponse
    {
        $this->authorize('create', GameMatch::class);

        $this->matches->createMatch($request->validated());

        return redirect()
            ->route('admin.matches.index')
            ->with('success', 'Match created successfully.');
    }

    public function show(GameMatch $match): View
    {
        $this->authorize('view', $match);

        $match->load([
            'edition', 'teamA.team', 'teamB.team', 'venue', 'tossWinner.team', 'winner.team',
            'firstInnings.battingTeam.team', 'firstInnings.bowlingTeam.team',
            'secondInnings.battingTeam.team', 'secondInnings.bowlingTeam.team',
        ]);
        $match->loadCount(['matchPlayers', 'innings']);

        $firstInningsPreview = null;

        if (! $match->firstInnings && $match->toss_winner_team_id && $match->toss_decision) {
            $teams = $this->innings->determineFirstInningsTeams($match);

            $firstInningsPreview = [
                'battingTeamName' => (int) $teams['batting_team_id'] === (int) $match->edition_team_a_id
                    ? $match->teamA->team->name
                    : $match->teamB->team->name,
                'bowlingTeamName' => (int) $teams['bowling_team_id'] === (int) $match->edition_team_a_id
                    ? $match->teamA->team->name
                    : $match->teamB->team->name,
            ];
        }

        $resultPreview = null;

        if ($match->firstInnings && $match->secondInnings
            && $match->firstInnings->status === 'completed' && $match->secondInnings->status === 'completed') {
            $resultPreview = $this->results->calculateResult($match->firstInnings, $match->secondInnings);
        }

        return view('admin.matches.show', [
            'match' => $match,
            'teamASelectedCount' => $match->matchPlayers()
                ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $match->edition_team_a_id))
                ->count(),
            'teamBSelectedCount' => $match->matchPlayers()
                ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $match->edition_team_b_id))
                ->count(),
            'canStartToss' => $this->matchFlow->canStartToss($match),
            'canStartMatch' => $this->matchFlow->canStartMatch($match),
            'canCancelMatch' => $this->matchFlow->canCancelMatch($match),
            'canAbandonMatch' => $this->matchFlow->canAbandonMatch($match),
            'canStartFirstInnings' => $this->innings->canStartFirstInnings($match),
            'canStartSecondInnings' => $this->innings->canStartSecondInnings($match),
            'firstInningsPreview' => $firstInningsPreview,
            'canFinalize' => $this->results->canFinalize($match),
            'resultPreview' => $resultPreview,
        ]);
    }

    public function edit(GameMatch $match): View
    {
        $this->authorize('update', $match);

        $match->load(['edition', 'teamA.team', 'teamB.team', 'venue']);

        $canChangeIdentity = $this->matches->canChangeFixtureIdentity($match);

        return view('admin.matches.edit', [
            'match' => $match,
            'canChangeIdentity' => $canChangeIdentity,
            'editions' => $canChangeIdentity ? Edition::openForParticipation()->orderByDesc('year')->get() : collect(),
            'editionTeams' => $canChangeIdentity ? $this->eligibleEditionTeams() : collect(),
            // The match's current venue must remain selectable even if it
            // has since been deactivated (only a NEW selection must be
            // active) — see UpdateGameMatchRequest.
            'venues' => Venue::query()
                ->where(function ($query) use ($match) {
                    $query->where('is_active', true);

                    if ($match->venue_id) {
                        $query->orWhere('id', $match->venue_id);
                    }
                })
                ->orderBy('name')
                ->get(),
            'stages' => GameMatch::STAGES,
        ]);
    }

    public function update(UpdateGameMatchRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('update', $match);

        $this->matches->updateMatch($match, $request->validated());

        return redirect()
            ->route('admin.matches.index')
            ->with('success', 'Match updated successfully.');
    }

    public function destroy(GameMatch $match): RedirectResponse
    {
        $this->authorize('delete', $match);

        if (! $this->matches->deleteMatch($match)) {
            return redirect()
                ->route('admin.matches.index')
                ->with('error', 'This match cannot be deleted because match data already exists.');
        }

        return redirect()
            ->route('admin.matches.index')
            ->with('success', 'Match deleted successfully.');
    }

    private function eligibleEditionTeams()
    {
        return EditionTeam::query()
            ->whereHas('team', fn ($query) => $query->where('is_active', true))
            ->with(['edition', 'team'])
            ->get();
    }
}
