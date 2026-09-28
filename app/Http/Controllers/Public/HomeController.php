<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\Video;
use App\Services\LiveMatch\LiveMatchService;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Illuminate\View\View;

/**
 * Public tournament homepage. Read-only — no authorization needed
 * (public pages are intentionally open), and no calculations of its
 * own: standings/statistics/live score come from the same
 * StandingsService/PlayerStatisticsService/LiveMatchService the admin
 * panel and the public Live Match Center already use.
 *
 * Match Centre priority (frozen for this pass): a single live match
 * outranks everything — its canonical score comes from LiveMatchService,
 * never a second calculation. With no live match, the soonest
 * scheduled/toss match becomes the headline instead. The most recent
 * completed match is shown alongside either case when one exists.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly StandingsService $standings,
        private readonly PlayerStatisticsService $statistics,
        private readonly LiveMatchService $liveMatch,
    ) {}

    public function __invoke(): View
    {
        $edition = Edition::current();

        $upcomingMatches = collect();
        $recentMatches = collect();
        $standings = collect();
        $topRunScorers = collect();
        $topWicketTakers = collect();
        $teams = collect();
        $featuredVideos = collect();
        $liveMatch = null;
        $liveMatchData = null;
        $nextMatch = null;
        $recentMatch = null;

        if ($edition) {
            $matchEagerLoads = [
                'teamA.team', 'teamB.team', 'venue',
                'firstInnings.battingTeam.team', 'secondInnings.battingTeam.team',
            ];

            $liveMatch = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->where('match_status', 'live')
                ->with($matchEagerLoads)
                ->orderBy('scheduled_at')
                ->first();

            if ($liveMatch) {
                $liveMatchData = $this->liveMatch->getLiveMatchData($liveMatch);
            } else {
                $nextMatch = GameMatch::query()
                    ->where('edition_id', $edition->id)
                    ->whereIn('match_status', ['scheduled', 'toss'])
                    ->with($matchEagerLoads)
                    ->orderBy('scheduled_at')
                    ->first();
            }

            $recentMatch = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->where('match_status', 'completed')
                ->with($matchEagerLoads)
                ->orderByDesc('scheduled_at')
                ->first();

            $upcomingMatches = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->whereIn('match_status', ['scheduled', 'toss', 'live'])
                ->with($matchEagerLoads)
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

            $teams = $edition->editionTeams()->with('team')->get();

            // Videos aren't edition-scoped, but the section only renders
            // inside the edition branch of the homepage (between Featured
            // Match and Points Table), so there's no point querying them
            // for the no-edition empty state.
            $featuredVideos = Video::query()->active()->ordered()->limit(3)->get();
        }

        return view('public.home', [
            'edition' => $edition,
            'liveMatch' => $liveMatch,
            'liveMatchData' => $liveMatchData,
            'nextMatch' => $nextMatch,
            'recentMatch' => $recentMatch,
            'upcomingMatches' => $upcomingMatches,
            'recentMatches' => $recentMatches,
            'standings' => $standings,
            'topRunScorers' => $topRunScorers,
            'topWicketTakers' => $topWicketTakers,
            'teams' => $teams,
            'featuredVideos' => $featuredVideos,
        ]);
    }
}
