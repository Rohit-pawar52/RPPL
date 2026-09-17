<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Services\Finance\ContributorRankingService;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Illuminate\View\View;

/**
 * Public edition listing/detail pages. Read-only, no authorization —
 * consumes the exact same StandingsService/PlayerStatisticsService the
 * admin panel uses, never a second calculation engine.
 */
class EditionController extends Controller
{
    public function __construct(
        private readonly StandingsService $standings,
        private readonly PlayerStatisticsService $statistics,
        private readonly ContributorRankingService $contributorRanking,
    ) {}

    public function index(): View
    {
        // A handful of editions at RPPL's scale — a plain ordered list
        // is enough, no pagination needed. withCount avoids loading full
        // relations merely to display counts.
        $editions = Edition::query()
            ->withCount(['editionTeams', 'matches'])
            ->orderByDesc('year')
            ->get();

        return view('public.editions.index', [
            'editions' => $editions,
        ]);
    }

    public function show(Edition $edition): View
    {
        $edition->loadCount(['editionTeams', 'matches']);

        $teams = $edition->editionTeams()->with('team')->get();

        $matches = GameMatch::query()
            ->where('edition_id', $edition->id)
            ->with([
                'teamA.team', 'teamB.team', 'venue',
                'firstInnings.battingTeam.team', 'secondInnings.battingTeam.team',
            ])
            ->orderByDesc('scheduled_at')
            ->limit(10)
            ->get();

        return view('public.editions.show', [
            'edition' => $edition,
            'teams' => $teams,
            'matches' => $matches,
            'standings' => $this->standings->getEditionStandings($edition)['standings'],
            'leaderboard' => $this->statistics->getEditionLeaderboard($edition),
            'records' => $this->statistics->getEditionRecords($edition),
            'contributorRanking' => $this->contributorRanking->getEditionRanking($edition),
        ]);
    }
}
