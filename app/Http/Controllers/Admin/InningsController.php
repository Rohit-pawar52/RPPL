<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchScoreUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Innings\CompleteInningsRequest;
use App\Http\Requests\Admin\Innings\ReopenInningsRequest;
use App\Http\Requests\Admin\Innings\SetUpOpeningStateRequest;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Services\Innings\InningsService;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Innings lifecycle workflow actions (start first innings, complete an
 * innings, start second innings). No generic CRUD — innings identity
 * (match_id/innings_number/batting_team_id/bowling_team_id) is derived
 * domain data, never a form field, and innings are never edited or
 * deleted through the admin panel.
 */
class InningsController extends Controller
{
    public function __construct(private readonly InningsService $innings) {}

    public function startFirst(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageInnings', $match);

        if (! $this->innings->startFirstInnings($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('The first innings cannot be started for this match right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        // Frozen S02 completion rule A: creating the innings never makes
        // it ready for ball entry by itself — go straight to its
        // explicit opening setup screen (same scoring page, which
        // detects the awaiting-setup state) rather than back to the
        // match page.
        return redirect()
            ->route('admin.matches.innings.score', [$match, $match->fresh()->firstInnings])
            ->with('success', __('First innings started — select the opening striker, non-striker, and bowler.'));
    }

    /**
     * Explicit Start Innings setup (frozen S02 completion rule A) —
     * confirms the opening striker, non-striker, and first bowler for an
     * innings that was just started. No Delivery is created; the same
     * reusable action serves both the first and second innings.
     */
    public function setupOpeningState(SetUpOpeningStateRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('manageInnings', $match);

        if (! $this->innings->setUpOpeningState(
            $match,
            $innings,
            (int) $request->validated('striker_match_player_id'),
            (int) $request->validated('non_striker_match_player_id'),
            (int) $request->validated('bowler_match_player_id'),
        )) {
            return redirect()
                ->route('admin.matches.innings.score', [$match, $innings])
                ->with('error', __('This innings cannot be set up right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', __('Innings is ready — scoring can begin.'));
    }

    public function complete(CompleteInningsRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        // Never allow an Innings from a different match to be acted on
        // through this match's URL.
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('manageInnings', $match);

        if (! $this->innings->completeInnings($match, $innings, $request->validated('reason'))) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('This innings cannot be completed right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Innings completed successfully.'));
    }

    /**
     * Reopen a completed innings (frozen S02 rule 16).
     */
    public function reopen(ReopenInningsRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('manageInnings', $match);

        if (! $this->innings->reopenInnings($match, $innings, $request->validated('reason'), $request->user())) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('This innings cannot be reopened right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', __('Innings reopened successfully.'));
    }

    public function startSecond(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageInnings', $match);

        if (! $this->innings->startSecondInnings($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', __('The second innings cannot be started for this match right now.'));
        }

        $this->broadcastMatchUpdated($match->id);

        // Second innings never becomes ready for ball entry merely
        // because the first completed (frozen S02 completion rule A) —
        // it independently needs its own explicit opening setup.
        return redirect()
            ->route('admin.matches.innings.score', [$match, $match->fresh()->secondInnings])
            ->with('success', __('Second innings started — select the opening striker, non-striker, and bowler.'));
    }

    /**
     * See ScoringController::broadcastMatchUpdated() for the full
     * transaction-safety/best-effort rationale — identical here.
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
