<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTeam;
use App\Models\EditionTransaction;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Finance\ContributorRankingService;
use Illuminate\View\View;

/**
 * Operational admin dashboard for the currently-relevant tournament
 * edition. Every figure is a direct count/grouped-count query against
 * existing tables — no new aggregation engine, no recalculated
 * results (match_result is read as MatchResultService already wrote
 * it, standings/statistics are intentionally out of scope here).
 *
 * Phase 3.42 added the payment-verification/finance/contribution
 * figures below, reusing existing data exactly as the Finance,
 * Contributions, and Player Registration admin screens already
 * calculate it — this controller never redefines those semantics.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ContributorRankingService $contributorRanking) {}

    public function __invoke(): View
    {
        $edition = $this->currentEdition();

        $registeredPlayers = 0;
        $paidRegistrations = 0;
        $pendingRegistrations = 0;
        $failedRegistrations = 0;
        $refundedRegistrations = 0;
        $paidRegistrationAmount = 0.0;
        $teamsCount = 0;
        $squadPlayersCount = 0;
        $matchesCount = 0;
        $liveMatchesCount = 0;
        $scheduledMatchesCount = 0;
        $completedMatchesCount = 0;
        $matchesNeedingAttention = collect();
        $recentResults = collect();
        $financeSummary = ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        $contributionTotal = 0.0;
        $contributionCount = 0;
        $recognizedContributorsCount = 0;

        if ($edition) {
            // One grouped query for both counts and the paid-amount
            // total — registration_fee is summed in SQL from each row's
            // own stored value, never edition->registration_fee times a
            // count, since historical rows may have charged a different
            // fee than the edition's current one.
            $registrationsByStatus = PlayerRegistration::query()
                ->where('edition_id', $edition->id)
                ->selectRaw('payment_status, count(*) as total, COALESCE(SUM(registration_fee), 0) as fee_total')
                ->groupBy('payment_status')
                ->get()
                ->keyBy('payment_status');

            $registeredPlayers = (int) $registrationsByStatus->sum('total');
            $paidRegistrations = (int) ($registrationsByStatus->get('paid')->total ?? 0);
            $pendingRegistrations = (int) ($registrationsByStatus->get('pending')->total ?? 0);
            $failedRegistrations = (int) ($registrationsByStatus->get('failed')->total ?? 0);
            $refundedRegistrations = (int) ($registrationsByStatus->get('refunded')->total ?? 0);
            $paidRegistrationAmount = (float) ($registrationsByStatus->get('paid')->fee_total ?? 0);

            $financeSummary = EditionTransaction::summaryForEdition($edition->id);

            $contributionStats = EditionContribution::query()
                ->where('edition_id', $edition->id)
                ->selectRaw('count(*) as total, COALESCE(SUM(amount), 0) as amount_total')
                ->first();
            $contributionCount = (int) $contributionStats->total;
            $contributionTotal = (float) $contributionStats->amount_total;

            // Reuses the existing canonical-identity ranking rather than
            // a plain distinct-count of committee_member_id/contributor_id,
            // so a CommitteeMember explicitly linked to a Contributor is
            // counted once, exactly like the public leaderboard.
            $recognizedContributorsCount = count($this->contributorRanking->getEditionRanking($edition));

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
            'failedRegistrations' => $failedRegistrations,
            'refundedRegistrations' => $refundedRegistrations,
            'paidRegistrationAmount' => $paidRegistrationAmount,
            'financeSummary' => $financeSummary,
            'contributionTotal' => $contributionTotal,
            'contributionCount' => $contributionCount,
            'recognizedContributorsCount' => $recognizedContributorsCount,
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
