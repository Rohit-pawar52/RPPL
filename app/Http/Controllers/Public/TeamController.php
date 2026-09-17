<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Team;
use App\Services\Statistics\StandingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public team directory/profile. Read-only, no authorization. Reuses
 * StandingsService for the tiny played/won/lost/tied summary rather
 * than duplicating standings rules.
 */
class TeamController extends Controller
{
    private const STATUS_PRIORITY = ['active' => 0, 'upcoming' => 1, 'completed' => 2];

    public function __construct(private readonly StandingsService $standings) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        $teams = Team::query()
            ->active()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('short_name', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('public.teams.index', [
            'teams' => $teams,
            'search' => $search,
        ]);
    }

    /**
     * Deliberately does NOT reject an inactive team — a direct link to a
     * historical team profile must remain usable.
     */
    public function show(Request $request, Team $team): View
    {
        $editionTeams = $team->editionTeams()->with('edition')->get();

        // Whitelisted against this team's own participation, exactly as
        // the player edition filter does — an edition_id the team never
        // participated in is ignored rather than trusted.
        $selectedEditionTeam = $request->filled('edition_id')
            ? $editionTeams->firstWhere('edition_id', $request->integer('edition_id'))
            : null;

        $selectedEditionTeam ??= $this->defaultEditionTeam($editionTeams);

        $squad = $selectedEditionTeam
            ? $selectedEditionTeam->teamPlayers()->with('playerRegistration.player')->get()
            : collect();

        $recentMatches = $selectedEditionTeam
            ? GameMatch::query()
                ->where(function ($query) use ($selectedEditionTeam) {
                    $query->where('edition_team_a_id', $selectedEditionTeam->id)
                        ->orWhere('edition_team_b_id', $selectedEditionTeam->id);
                })
                ->with(['teamA.team', 'teamB.team', 'venue'])
                ->orderByDesc('scheduled_at')
                ->limit(10)
                ->get()
            : collect();

        $record = null;

        if ($selectedEditionTeam) {
            $standings = $this->standings->getEditionStandings($selectedEditionTeam->edition);
            $record = collect($standings['standings'])->firstWhere('edition_team.id', $selectedEditionTeam->id);
        }

        return view('public.teams.show', [
            'team' => $team,
            'editionTeams' => $editionTeams,
            'selectedEditionTeam' => $selectedEditionTeam,
            'squad' => $squad,
            'recentMatches' => $recentMatches,
            'record' => $record,
        ]);
    }

    /**
     * No edition_id given: prefer an active edition's participation,
     * then upcoming, then the most recent completed one — a simple,
     * deterministic rule rather than a generalized selection service.
     */
    private function defaultEditionTeam($editionTeams)
    {
        return $editionTeams->sort(function ($a, $b) {
            $priorityA = self::STATUS_PRIORITY[$a->edition->status] ?? 99;
            $priorityB = self::STATUS_PRIORITY[$b->edition->status] ?? 99;

            return $priorityA <=> $priorityB ?: $b->edition->year <=> $a->edition->year;
        })->first();
    }
}
