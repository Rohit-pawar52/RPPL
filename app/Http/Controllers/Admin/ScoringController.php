<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchScoreUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scoring\AwardPenaltyRunsRequest;
use App\Http\Requests\Admin\Scoring\ChangeBowlerMidOverRequest;
use App\Http\Requests\Admin\Scoring\ChangeStrikeRequest;
use App\Http\Requests\Admin\Scoring\CorrectDeliveryRequest;
use App\Http\Requests\Admin\Scoring\RetireBatterRequest;
use App\Http\Requests\Admin\Scoring\SelectNewBatterRequest;
use App\Http\Requests\Admin\Scoring\SelectOverBowlerRequest;
use App\Http\Requests\Admin\Scoring\StoreDeliveryRequest;
use App\Models\Delivery;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Services\Innings\InningsService;
use App\Services\Scoring\DeliveryService;
use App\Services\Scoring\LiveScoringStateService;
use App\Services\Scoring\ScoringEventService;
use App\Services\Scoring\UndoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * The ball-by-ball scoring screen and its mutating actions. Kept
 * separate from InningsController, which owns innings lifecycle
 * (start/complete/reopen), not per-ball scoring.
 */
class ScoringController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly ScoringEventService $scoringEvents,
        private readonly InningsService $inningsService,
        private readonly LiveScoringStateService $liveState,
        private readonly UndoService $undo,
    ) {}

    /**
     * The canonical scorer-state JSON (frozen S02 rules 50-61) — polled
     * by the scoring screen's JS, and used to recover state after a
     * refresh/reconnect (rule 53) exactly like the public Live Match
     * Center's own /live-data endpoint already works for spectators.
     */
    public function scoreData(GameMatch $match, Innings $innings): JsonResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        return response()->json($this->liveState->getState($match, $innings));
    }

    public function show(GameMatch $match, Innings $innings): View
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $innings->load(['battingTeam.team', 'bowlingTeam.team']);

        $recentDeliveries = Delivery::query()
            ->where('innings_id', $innings->id)
            ->with([
                'striker.teamPlayer.playerRegistration.player',
                'bowler.teamPlayer.playerRegistration.player',
                'dismissedPlayer.teamPlayer.playerRegistration.player',
            ])
            ->orderByDesc('delivery_sequence')
            ->limit(15)
            ->get();

        $expectedBattingState = $this->deliveries->expectedBattingState($innings);
        $previousOverBowlerId = $expectedBattingState['awaiting_new_over_bowler']
            ? $this->deliveries->bowlerOfPreviousOver($innings)
            : null;

        // Frozen S02 completion rule A: an innings created but never
        // given its explicit opening setup shows that setup screen
        // instead of the ball-entry form — never implicitly bundled into
        // the first delivery.
        $awaitingSetup = $this->inningsService->canSetUpOpeningState($match, $innings);

        return view('admin.scoring.show', [
            'match' => $match,
            'innings' => $innings,
            'battingMatchPlayers' => $this->eligibleMatchPlayers($match, $innings->batting_team_id),
            'bowlingMatchPlayers' => $this->eligibleMatchPlayers($match, $innings->bowling_team_id),
            'recentDeliveries' => $recentDeliveries,
            'canRecordDelivery' => $this->deliveries->canRecordDelivery($match, $innings),
            'isOverLimitReached' => $this->deliveries->isOverLimitReached($match, $innings),
            'awaitingSetup' => $awaitingSetup,
            'expectedBattingState' => $expectedBattingState,
            'isFreeHit' => $this->deliveries->isFreeHit($innings),
            'previousOverBowlerId' => $previousOverBowlerId,
            'wicketTypes' => Delivery::WICKET_TYPES,
            'liveState' => $this->liveState->getState($match, $innings),
        ]);
    }

    /**
     * Change Strike (frozen S02 rule 20) — corrects the actual striker/
     * non-striker ends without a delivery/runs/wickets change.
     */
    public function changeStrike(ChangeStrikeRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $this->scoringEvents->changeStrike(
            $match,
            $innings,
            (int) $request->validated('striker_match_player_id'),
            (int) $request->validated('non_striker_match_player_id'),
            $request->validated('reason'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', 'Strike corrected successfully.');
    }

    /**
     * Retired Hurt / Retired Out (frozen S02 rules 4/5).
     */
    public function retireBatter(RetireBatterRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $batter = MatchPlayer::query()->where('match_id', $match->id)->findOrFail($request->validated('match_player_id'));

        $this->scoringEvents->retireBatter(
            $match,
            $innings,
            $batter,
            $request->validated('type'),
            $request->validated('reason'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        $message = $request->validated('type') === 'out'
            ? 'Batter recorded as retired out.'
            : 'Batter recorded as retired hurt.';

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', $message);
    }

    /**
     * Penalty runs (frozen S02 rule 6) — a separate scoring event, never
     * a Delivery.
     */
    public function awardPenaltyRuns(AwardPenaltyRunsRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $awardedTeam = EditionTeam::findOrFail($request->validated('awarded_team_id'));

        $this->scoringEvents->awardPenaltyRuns(
            $match,
            $innings,
            $awardedTeam,
            $request->validated('reason'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', 'Penalty runs recorded.');
    }

    /**
     * Select New Batter (frozen S02 completion rule C).
     */
    public function selectNewBatter(SelectNewBatterRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $this->scoringEvents->selectNewBatter(
            $match,
            $innings,
            (int) $request->validated('match_player_id'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', 'New batter selected.');
    }

    /**
     * Select Over Bowler (frozen S02 completion rule D).
     */
    public function selectOverBowler(SelectOverBowlerRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $this->scoringEvents->selectOverBowler(
            $match,
            $innings,
            (int) $request->validated('bowler_match_player_id'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', 'Bowler selected for the new over.');
    }

    /**
     * Change Bowler Mid-Over (frozen S02 completion rule E).
     */
    public function changeBowlerMidOver(ChangeBowlerMidOverRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $this->scoringEvents->changeBowlerMidOver(
            $match,
            $innings,
            (int) $request->validated('bowler_match_player_id'),
            $request->validated('reason'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', 'Bowler changed mid-over.');
    }

    /**
     * JSON when the client asks for it (the one-click quick-scoring pad,
     * frozen rules 50/51) — otherwise the original redirect, so the
     * plain-form fallback keeps working with JavaScript disabled.
     * idempotency_key (rule 51) is validated by StoreDeliveryRequest and
     * checked inside DeliveryService::recordDelivery() itself, ahead of
     * any other eligibility check, so a safe retry of an already-
     * succeeded submission can never fail merely because the innings
     * moved on in the meantime.
     */
    public function store(StoreDeliveryRequest $request, GameMatch $match, Innings $innings): RedirectResponse|JsonResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        if (! $this->deliveries->canRecordDelivery($match, $innings)) {
            $message = $this->deliveries->isOverLimitReached($match, $innings)
                ? 'The overs limit for this innings has been reached.'
                : 'This innings can no longer be scored.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()
                ->route('admin.matches.innings.score', [$match, $innings])
                ->with('error', $message);
        }

        $this->deliveries->recordDelivery($match, $innings, $request->validated());

        // One signal even when this same call also auto-completed the
        // innings (Phase 3.32) — that transition happened inside the
        // same recordDelivery() transaction, not a second mutation.
        $this->broadcastMatchUpdated($match->id);

        $message = $innings->fresh()->status === 'completed'
            ? 'Delivery recorded. Innings completed.'
            : 'Delivery recorded successfully.';

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'state' => $this->liveState->getState($match, $innings)]);
        }

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', $message);
    }

    /**
     * Universal Undo (frozen rule 42) — reverses whichever reversible
     * scoring action is chronologically latest for this innings (a
     * Delivery, or one of ScoringEvent::UNDOABLE_TYPES), not only the
     * latest Delivery. See UndoService for exactly which action types
     * are reversible and why the rest are deliberately excluded.
     */
    public function undoLatest(Request $request, GameMatch $match, Innings $innings): RedirectResponse|JsonResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        $result = $this->undo->undoLastAction($match, $innings, $request->user());

        if (! $result['undone']) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $result['message']], 422);
            }

            return redirect()
                ->route('admin.matches.innings.score', [$match, $innings])
                ->with('error', $result['message']);
        }

        $this->broadcastMatchUpdated($match->id);

        if ($request->wantsJson()) {
            return response()->json(['message' => $result['message'], 'state' => $this->liveState->getState($match, $innings)]);
        }

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', $result['message']);
    }

    /**
     * Quick correction of one of the latest 3 deliveries (frozen rules
     * 43/44/46) — JSON only, this is a new AJAX-only affordance with no
     * plain-form fallback (there was no prior UI for it to degrade to).
     */
    public function correctDelivery(CorrectDeliveryRequest $request, GameMatch $match, Innings $innings, Delivery $delivery): JsonResponse
    {
        abort_unless($innings->match_id === $match->id, 404);
        abort_unless($delivery->innings_id === $innings->id, 404);

        $this->authorize('score', $match);

        $this->deliveries->correctDelivery(
            $match,
            $innings,
            $delivery,
            $request->validated(),
            $request->validated('reason'),
            $request->user(),
        );

        $this->broadcastMatchUpdated($match->id);

        return response()->json([
            'message' => 'Delivery corrected successfully.',
            'state' => $this->liveState->getState($match, $innings),
        ]);
    }

    /**
     * Publishes the public "this match changed" realtime signal. Called
     * only after the triggering domain mutation has already committed
     * successfully — never before, and never for a rejected/failed
     * action. Real-time broadcasting is an enhancement layered on top of
     * already-correct scoring, never part of its correctness: since this
     * runs after MatchScoreUpdated's own transaction has returned, its
     * ShouldBroadcast job is enqueued synchronously and inline (Laravel
     * defers a ShouldBroadcast job only while an outer transaction is
     * still open — see Illuminate\Database\DatabaseTransactionsManager
     * ::addCallback()), so a transient broadcasting/queue infrastructure
     * failure here must never surface as if the already-successful
     * scoring action itself had failed. Existing polling remains the
     * reliable fallback regardless.
     */
    private function broadcastMatchUpdated(int $matchId): void
    {
        try {
            event(new MatchScoreUpdated($matchId));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return Collection<int, MatchPlayer>
     */
    private function eligibleMatchPlayers(GameMatch $match, int $editionTeamId): Collection
    {
        return MatchPlayer::query()
            ->where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeamId))
            ->with('teamPlayer.playerRegistration.player')
            ->get();
    }
}
