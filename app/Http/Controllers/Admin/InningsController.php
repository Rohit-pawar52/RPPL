<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchScoreUpdated;
use App\Http\Controllers\Controller;
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
                ->with('error', 'The first innings cannot be started for this match right now.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'First innings started successfully.');
    }

    public function complete(GameMatch $match, Innings $innings): RedirectResponse
    {
        // Never allow an Innings from a different match to be acted on
        // through this match's URL.
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('manageInnings', $match);

        if (! $this->innings->completeInnings($match, $innings)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', 'This innings cannot be completed right now.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Innings completed successfully.');
    }

    public function startSecond(GameMatch $match): RedirectResponse
    {
        $this->authorize('manageInnings', $match);

        if (! $this->innings->startSecondInnings($match)) {
            return redirect()
                ->route('admin.matches.show', $match)
                ->with('error', 'The second innings cannot be started for this match right now.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.show', $match)
            ->with('success', 'Second innings started successfully.');
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
