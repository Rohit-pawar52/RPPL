<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\News;
use App\Models\Photo;
use App\Models\Video;
use App\Services\Auction\AuctionStateService;
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
 * Top to bottom: sponsor banner, one scrolling row of match cards (the
 * live/next one or two, a sponsor card, the latest results), Videos / News
 * / Photos cards, a sponsor banner and the current season's summary
 * (points table and the top few of each stats board).
 */
class HomeController extends Controller
{
    /**
     * Matches shown on each side of the sponsor card in the match row.
     */
    private const UPCOMING_IN_ROW = 2;

    private const RECENT_IN_ROW = 3;

    /**
     * Items shown in each of the Videos / News / Photos cards.
     */
    private const MEDIA_ITEMS = 3;

    /**
     * Rows on each season-summary board (the stats pages list more).
     */
    private const SUMMARY_ROWS = 5;

    public function __construct(
        private readonly StandingsService $standings,
        private readonly PlayerStatisticsService $statistics,
        private readonly LiveMatchService $liveMatch,
        private readonly AuctionStateService $auctions,
    ) {}

    public function __invoke(): View
    {
        $edition = Edition::current();

        $upcomingMatches = collect();
        $recentMatches = collect();
        $standings = collect();
        $highlights = [];
        $liveMatchData = null;

        if ($edition) {
            $matchEagerLoads = [
                'teamA.team', 'teamB.team', 'venue',
                'firstInnings.battingTeam.team', 'secondInnings.battingTeam.team',
            ];

            // Live first, then the soonest scheduled ones.
            $upcomingMatches = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->whereIn('match_status', ['scheduled', 'toss', 'live'])
                ->with($matchEagerLoads)
                ->orderByRaw("case when match_status = 'live' then 0 else 1 end")
                ->orderBy('scheduled_at')
                ->limit(self::UPCOMING_IN_ROW)
                ->get();

            $recentMatches = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->where('match_status', 'completed')
                ->with($matchEagerLoads)
                ->orderByDesc('scheduled_at')
                ->limit(self::RECENT_IN_ROW)
                ->get();

            // The one live match gets its chase line from the same payload
            // the Live page uses.
            $liveMatch = $upcomingMatches->firstWhere('match_status', 'live');
            $liveMatchData = $liveMatch ? $this->liveMatch->getLiveMatchData($liveMatch) : null;

            $standings = collect($this->standings->getEditionStandings($edition)['standings']);
            $highlights = $this->statistics->getEditionHighlights($edition, self::SUMMARY_ROWS);
        }

        return view('public.home', [
            'edition' => $edition,
            'auctionCard' => $this->auctions->homeCard(),
            'upcomingMatches' => $upcomingMatches,
            'recentMatches' => $recentMatches,
            'liveMatchId' => $upcomingMatches->firstWhere('match_status', 'live')?->id,
            'liveMatchData' => $liveMatchData,
            'standings' => $standings,
            'highlights' => $highlights,
            // Not edition-scoped: the tournament's own clips, news and photos.
            'latestVideos' => Video::query()->active()->ordered()->limit(self::MEDIA_ITEMS)->get(),
            'latestNews' => News::query()->visible()->with('coverImage')->ordered()->limit(self::MEDIA_ITEMS)->get(),
            'latestPhotos' => Photo::query()->active()->ordered()->limit(self::MEDIA_ITEMS)->get(),
        ]);
    }
}
