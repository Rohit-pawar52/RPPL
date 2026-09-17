<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Illuminate\View\View;

/**
 * Public tournament homepage. Read-only — no authorization needed
 * (public pages are intentionally open), and no calculations of its
 * own: standings/statistics come from the same StandingsService/
 * PlayerStatisticsService the admin panel already uses.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly StandingsService $standings,
        private readonly PlayerStatisticsService $statistics,
    ) {}

    public function __invoke(): View
    {
        $edition = $this->currentEdition();

        $upcomingMatches = collect();
        $recentMatches = collect();
        $standings = collect();
        $topRunScorers = collect();
        $topWicketTakers = collect();

        if ($edition) {
            $upcomingMatches = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->whereIn('match_status', ['scheduled', 'toss', 'live'])
                ->with([
                    'teamA.team', 'teamB.team', 'venue',
                    'firstInnings.battingTeam.team', 'secondInnings.battingTeam.team',
                ])
                ->orderBy('scheduled_at')
                ->limit(5)
                ->get();

            $recentMatches = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->where('match_status', 'completed')
                ->with(['teamA.team', 'teamB.team'])
                ->orderByDesc('scheduled_at')
                ->limit(5)
                ->get();

            $standings = collect($this->standings->getEditionStandings($edition)['standings'])->take(5);

            $leaderboard = $this->statistics->getEditionLeaderboard($edition, 3);
            $topRunScorers = collect($leaderboard['topRunScorers']);
            $topWicketTakers = collect($leaderboard['topWicketTakers']);
        }

        return view('public.home', [
            'edition' => $edition,
            'upcomingMatches' => $upcomingMatches,
            'recentMatches' => $recentMatches,
            'standings' => $standings,
            'topRunScorers' => $topRunScorers,
            'topWicketTakers' => $topWicketTakers,
        ]);
    }

    /**
     * Deterministic homepage edition selection: the active edition if
     * one exists, otherwise the soonest upcoming edition, otherwise the
     * most recently completed one, otherwise null (clean empty state —
     * never assumed to always exist).
     */
    private function currentEdition(): ?Edition
    {
        return Edition::where('status', 'active')->latest('year')->first()
            ?? Edition::where('status', 'upcoming')->orderBy('year')->first()
            ?? Edition::where('status', 'completed')->latest('year')->first();
    }
}
