<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersAdminTables;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameMatchController extends Controller
{
    use FiltersAdminTables;

    private const ALLOWED_SORTS = ['scheduled_at', 'match_status'];

    public function __construct(
        private readonly GameMatchService $matches,
        private readonly MatchFlowService $matchFlow,
        private readonly InningsService $innings,
        private readonly MatchResultService $results,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GameMatch::class);

        $dateRange = $this->validateDateRange($request);
        $filters = $request->only(['search', 'edition_id', 'match_status']) + $dateRange;
        [$sort, $direction] = $this->allowedSort($request, self::ALLOWED_SORTS, 'scheduled_at');
        $perPage = $this->allowedPerPage($request);

        $matches = $this->matchQuery($filters)
            ->with(['edition', 'teamA.team', 'teamB.team', 'venue'])
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.matches.index', [
            'matches' => $matches,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
        ]);
    }

    /**
     * Streamed UTF-8 CSV of the same filtered set index() shows — no
     * spreadsheet package exists in this project, and one row per match
     * at RPPL's scale doesn't justify adding one. Honors exactly the
     * same query string as the index (search/edition_id/match_status/
     * date range), so there is only ever one place filter rules are
     * defined. chunkById() (rather than loading everything at once)
     * keeps this safe if a season's fixture count ever grows.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', GameMatch::class);

        $filters = $request->only(['search', 'edition_id', 'match_status']) + $this->validateDateRange($request);

        return $this->streamMatchesCsv(
            $this->matchQuery($filters)->with(['edition', 'teamA.team', 'teamB.team', 'venue']),
            $this->exportFilename($filters)
        );
    }

    /**
     * Exports exactly the rows explicitly checked on the current index
     * page — never "every record matching the current filters" (that is
     * what export() above already does). Ignores $filters entirely:
     * selected_ids take precedence over the ambient filter set, but each
     * id must still be a real match (`exists:` rule below) so an
     * authorized admin/scorer can only ever export rows that genuinely
     * exist — viewAny is the same gate the index/export routes already
     * use, so this doesn't open any access they didn't already have.
     */
    public function exportSelected(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', GameMatch::class);

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:matches,id'],
        ]);

        return $this->streamMatchesCsv(
            GameMatch::query()
                ->whereIn('id', $validated['selected_ids'])
                ->with(['edition', 'teamA.team', 'teamB.team', 'venue']),
            'rppl-matches-selected.csv'
        );
    }

    private function streamMatchesCsv(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Excel-friendly UTF-8 BOM so accented team/venue names render
            // correctly when the file is opened directly in Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Match #', 'Edition', 'Team A', 'Team B', 'Venue', 'Scheduled At', 'Status', 'Stage', 'Result',
            ]);

            $query->chunkById(200, function ($matches) use ($handle) {
                foreach ($matches as $match) {
                    fputcsv($handle, [
                        $match->match_number,
                        $match->edition->name,
                        $match->teamA->team->name,
                        $match->teamB->team->name,
                        $match->venue->name ?? '',
                        $match->scheduled_at?->format('Y-m-d H:i') ?? '',
                        ucfirst($match->match_status),
                        $match->match_stage ? ucfirst(str_replace('_', ' ', $match->match_stage)) : '',
                        $match->match_result ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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

    /**
     * The single source of truth for match filtering, shared by index()
     * and export() so the two can never quietly diverge. $filters values
     * are whitelisted exactly as before: match_status must be one of the
     * real enum values, never an arbitrary column/value from the request.
     *
     * @param  array<string, mixed>  $filters
     */
    private function matchQuery(array $filters): Builder
    {
        return GameMatch::query()
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
            ->tap(fn ($query) => $this->dateRangeFilter($query, 'scheduled_at', $filters['from_date'] ?? null, $filters['to_date'] ?? null));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportFilename(array $filters): string
    {
        $editionId = $filters['edition_id'] ?? null;
        $edition = $editionId ? Edition::find($editionId) : null;

        return $edition
            ? "rppl-matches-{$edition->year}.csv"
            : 'rppl-matches.csv';
    }
}
