<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use Illuminate\View\View;

/**
 * Operational admin dashboard for the currently-relevant tournament
 * edition. Every figure is a direct count/grouped-count query against
 * existing tables — no new aggregation engine, no recalculated
 * results (match_result is read as MatchResultService already wrote
 * it, standings/statistics are intentionally out of scope here).
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $edition = $this->currentEdition();

        $registeredPlayers = 0;
        $paidRegistrations = 0;
        $pendingRegistrations = 0;
        $teamsCount = 0;
        $squadPlayersCount = 0;
        $matchesCount = 0;
        $liveMatchesCount = 0;
        $scheduledMatchesCount = 0;
        $completedMatchesCount = 0;
        $matchesNeedingAttention = collect();
        $recentResults = collect();

        if ($edition) {
            $registrationsByStatus = PlayerRegistration::query()
                ->where('edition_id', $edition->id)
                ->selectRaw('payment_status, count(*) as total')
                ->groupBy('payment_status')
                ->pluck('total', 'payment_status');

            $registeredPlayers = $registrationsByStatus->sum();
            $paidRegistrations = $registrationsByStatus->get('paid', 0);
            $pendingRegistrations = $registrationsByStatus->get('pending', 0);

            $teamsCount = EditionTeam::query()->where('edition_id', $edition->id)->count();

            $squadPlayersCount = TeamPlayer::query()
                ->whereHas('editionTeam', fn ($query) => $query->where('edition_id', $edition->id))
                ->count();

            $matchesByStatus = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->selectRaw('match_status, count(*) as total')
                ->groupBy('match_status')
                ->pluck('total', 'match_status');

            $matchesCount = $matchesByStatus->sum();
            $liveMatchesCount = $matchesByStatus->get('live', 0);
            $scheduledMatchesCount = $matchesByStatus->get('scheduled', 0);
            $completedMatchesCount = $matchesByStatus->get('completed', 0);

            // Bounded, priority-ordered (live, then toss, then nearest
            // upcoming) rather than a single ORDER BY — match_status
            // priority isn't a portable SQL expression across the
            // MySQL/SQLite connections this project already runs on.
            $matchesNeedingAttention = GameMatch::query()->where('edition_id', $edition->id)->where('match_status', 'live')
                ->with(['teamA.team', 'teamB.team', 'venue'])->orderBy('scheduled_at')->get()
                ->concat(
                    GameMatch::query()->where('edition_id', $edition->id)->where('match_status', 'toss')
                        ->with(['teamA.team', 'teamB.team', 'venue'])->orderBy('scheduled_at')->get()
                )
                ->concat(
                    GameMatch::query()->where('edition_id', $edition->id)->where('match_status', 'scheduled')
                        ->with(['teamA.team', 'teamB.team', 'venue'])->orderBy('scheduled_at')->limit(8)->get()
                )
                ->take(8);

            $recentResults = GameMatch::query()
                ->where('edition_id', $edition->id)
                ->where('match_status', 'completed')
                ->with(['teamA.team', 'teamB.team'])
                ->orderByDesc('scheduled_at')
                ->limit(5)
                ->get();
        }

        return view('admin.dashboard.index', [
            'edition' => $edition,
            'registeredPlayers' => $registeredPlayers,
            'paidRegistrations' => $paidRegistrations,
            'pendingRegistrations' => $pendingRegistrations,
            'teamsCount' => $teamsCount,
            'squadPlayersCount' => $squadPlayersCount,
            'matchesCount' => $matchesCount,
            'liveMatchesCount' => $liveMatchesCount,
            'scheduledMatchesCount' => $scheduledMatchesCount,
            'completedMatchesCount' => $completedMatchesCount,
            'matchesNeedingAttention' => $matchesNeedingAttention,
            'recentResults' => $recentResults,
        ]);
    }

    /**
     * Same deterministic rule as the public homepage (Phase 3.18):
     * active edition, else soonest upcoming, else most recently
     * completed, else none. Duplicated locally (3 lines) rather than
     * extracted into a shared service — reusing Public\HomeController's
     * private method directly would couple an Admin controller to a
     * Public one for no real benefit at this size.
     */
    private function currentEdition(): ?Edition
    {
        return Edition::where('status', 'active')->latest('year')->first()
            ?? Edition::where('status', 'upcoming')->orderBy('year')->first()
            ?? Edition::where('status', 'completed')->latest('year')->first();
    }
}
