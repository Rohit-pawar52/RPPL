<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchScoreUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GameMatch\RecordTossRequest;
use App\Models\GameMatch;
use App\Services\GameMatch\MatchFlowService;
use App\Services\GameMatch\MatchResultService;
use Illuminate\Http\RedirectResponse;
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
    ) {}

    public function startToss(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageMatchFlow', $match);

        if (! $this->matchFlow->startToss($match)) {
            $message = $match->fresh()->match_status !== 'scheduled'
                ? 'This match has already started and can no longer be modified.'
                : 'Both teams must have selected players before starting the toss.';

            return redirect()->route('admin.matches.show', $match)->with('error', $message);
        }

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Toss phase started successfully.');
    }

    public function recordToss(RecordTossRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('manageMatchFlow', $match);

        if (! $this->matchFlow->recordToss($match, $request->validated())) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', 'The toss can only be recorded during the toss phase.');
        }

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Toss recorded successfully.');
    }

    public function startMatch(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageMatchFlow', $match);

        if (! $this->matchFlow->startMatch($match)) {
            $fresh = $match->fresh();

            $message = match (true) {
                $fresh->match_status !== 'toss' => 'This match has already started and can no longer be modified.',
                ! $fresh->toss_winner_team_id || ! $fresh->toss_decision => 'Record the toss winner and decision before starting the match.',
                default => 'Both teams must have selected players before starting the match.',
            };

            return redirect()->route('admin.matches.show', $match)->with('error', $message);
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Match started successfully.');
    }

    public function cancel(GameMatch $match): RedirectResponse
    {
        $this->authorize('cancelMatch', $match);

        if (! $this->matchFlow->cancelMatch($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', 'This match can no longer be cancelled.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Match cancelled successfully.');
    }

    public function abandon(GameMatch $match): RedirectResponse
    {
        $this->authorize('abandonMatch', $match);

        if (! $this->matchFlow->abandonMatch($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', 'This match cannot be abandoned right now.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Match abandoned successfully.');
    }

    public function finalize(GameMatch $match): RedirectResponse
    {
        $this->authorize('finalizeResult', $match);

        if (! $this->results->finalizeMatch($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', 'This match cannot be finalized right now.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Match finalized successfully.');
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
