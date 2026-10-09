<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchScoreUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameMatch\RecordTossRequest;
use App\Http\Requests\Admin\GameMatch\ReopenMatchRequest;
use App\Http\Requests\Admin\GameMatch\SuperOverResultRequest;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Services\GameMatch\MatchFlowService;
use App\Services\GameMatch\MatchResultService;
use App\Services\MatchResult\MatchResultNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Match-day workflow actions from setup through result: start toss,
 * record/correct toss, start match, and (Phase 3.15) finalize the
 * match result. Deliberately kept separate from GameMatchController,
 * which owns fixture scheduling CRUD — mixing this workflow into that
 * controller would make its authorization story (admin-only CRUD vs.
 * admin+scorer match-day actions) much harder to read. Finalization
 * lives here rather than in a new controller because it has the exact
 * same shape as every other action already here: a single POST with no
 * body, gated by a canX() check, redirecting back to the match page.
 */
class MatchFlowController extends Controller
{
    public function __construct(
        private readonly MatchFlowService $matchFlow,
        private readonly MatchResultService $results,
        private readonly MatchResultNotificationService $resultNotifications,
    ) {}

    public function startToss(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageMatchFlow', $match);

        if (! $this->matchFlow->startToss($match)) {
            $message = $match->fresh()->match_status !== 'scheduled'
                ? __('This match has already started and can no longer be modified.')
                : __('Both teams must have selected players before starting the toss.');

            return redirect()->route('admin.matches.show', $match)->with('error', $message);
        }

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Toss phase started successfully.'));
    }

    public function recordToss(RecordTossRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('manageMatchFlow', $match);

        if (! $this->matchFlow->recordToss($match, $request->validated())) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('The toss can only be recorded during the toss phase.'));
        }

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Toss recorded successfully.'));
    }

    public function startMatch(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageMatchFlow', $match);

        if (! $this->matchFlow->startMatch($match)) {
            $fresh = $match->fresh();

            $message = match (true) {
                $fresh->match_status !== 'toss' => __('This match has already started and can no longer be modified.'),
                ! $fresh->toss_winner_team_id || ! $fresh->toss_decision => __('Record the toss winner and decision before starting the match.'),
                default => __('Both teams must have selected players before starting the match.'),
            };

            return redirect()->route('admin.matches.show', $match)->with('error', $message);
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Match started successfully.'));
    }

    public function cancel(GameMatch $match): RedirectResponse
    {
        $this->authorize('cancelMatch', $match);

        if (! $this->matchFlow->cancelMatch($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('This match can no longer be cancelled.'));
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Match cancelled successfully.'));
    }

    public function abandon(GameMatch $match): RedirectResponse
    {
        $this->authorize('abandonMatch', $match);

        if (! $this->matchFlow->abandonMatch($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('This match cannot be abandoned right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Match abandoned successfully.'));
    }

    public function finalize(Request $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('finalizeResult', $match);

        if (! $this->results->finalizeMatch($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('This match cannot be finalized right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        // Best-effort, strictly AFTER finalizeMatch()'s own transaction
        // has already committed — see MatchResultNotificationService's
        // docblock. A failed attempt here never affects the finalized
        // result above; the admin can retry via "Send Result
        // Notification" on the match page.
        $this->resultNotifications->dispatchIfDue($match->id, $request->user());

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Match finalized successfully.'));
    }

    /**
     * Tied Match / Super Over (frozen S02 rule 7).
     */
    public function recordSuperOverResult(SuperOverResultRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('finalizeResult', $match);

        $winner = EditionTeam::findOrFail($request->validated('winner_team_id'));

        if (! $this->results->recordSuperOverResult($match, $winner, $request->validated('reason'), $request->user())) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('A Super Over result can only be recorded for a match tied after both innings are completed.'));
        }

        $this->broadcastMatchUpdated($match->id);

        $this->resultNotifications->dispatchIfDue($match->id, $request->user());

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Super Over result recorded successfully.'));
    }

    /**
     * Manual recovery (only ever needed if the automatic attempt right
     * after finalize()/recordSuperOverResult() failed to queue, e.g. a
     * transient queue-connection issue) — reuses the exact same atomic
     * claim, so this is a no-op (returns to the page with an explanatory
     * message) if the notification was already dispatched or the match
     * isn't eligible.
     */
    public function resendResultNotification(Request $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('finalizeResult', $match);

        $dispatched = $this->resultNotifications->dispatchIfDue($match->id, $request->user());

        return redirect()
            ->route('admin.matches.show', $match)
            ->with($dispatched ? 'success' : 'error', $dispatched
                ? __('Result notification queued.')
                : __('Result notification could not be sent right now (it may already have been sent, or the match is not eligible).'));
    }

    /**
     * Reopen a finalized match (frozen S02 rule 17) — admin only.
     */
    public function reopen(ReopenMatchRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('reopenResult', $match);

        if (! $this->results->reopenMatch($match, $request->validated('reason'), $request->user())) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('This match cannot be reopened right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Match reopened for correction.'));
    }

    /**
     * See ScoringController::broadcastMatchUpdated() for the full
     * transaction-safety/best-effort rationale — identical here.
     * Deliberately not called from startToss()/recordToss(): Phase
     * 3.37A confirmed toss data isn't part of the public live JSON
     * payload, so there's nothing for a connected client to refresh yet.
     */
    private function broadcastMatchUpdated(int $matchId): void
    {
        try {
            event(new MatchScoreUpdated($matchId));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
