<?php

namespace App\Http\Controllers\Admin;

use App\Events\MatchScoreUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scoring\StoreDeliveryRequest;
use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Services\Scoring\DeliveryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/**
 * The ball-by-ball scoring screen and its two mutating actions. Kept
 * separate from InningsController (Phase 3.12), which owns innings
 * lifecycle (start/complete), not per-ball scoring.
 */
class ScoringController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

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

        return view('admin.scoring.show', [
            'match' => $match,
            'innings' => $innings,
            'battingMatchPlayers' => $this->eligibleMatchPlayers($match, $innings->batting_team_id),
            'bowlingMatchPlayers' => $this->eligibleMatchPlayers($match, $innings->bowling_team_id),
            'recentDeliveries' => $recentDeliveries,
            'canRecordDelivery' => $this->deliveries->canRecordDelivery($match, $innings),
            'isOverLimitReached' => $this->deliveries->isOverLimitReached($match, $innings),
            'expectedBattingState' => $this->deliveries->expectedBattingState($innings),
            'wicketTypes' => Delivery::WICKET_TYPES,
            'extraTypes' => Delivery::EXTRA_TYPES,
        ]);
    }

    public function store(StoreDeliveryRequest $request, GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        if (! $this->deliveries->canRecordDelivery($match, $innings)) {
            return redirect()
                ->route('admin.matches.innings.score', [$match, $innings])
                ->with('error', $this->deliveries->isOverLimitReached($match, $innings)
                    ? 'The overs limit for this innings has been reached.'
                    : 'This innings can no longer be scored.');
        }

        $this->deliveries->recordDelivery($match, $innings, $request->validated());

        // One signal even when this same call also auto-completed the
        // innings (Phase 3.32) — that transition happened inside the
        // same recordDelivery() transaction, not a second mutation.
        $this->broadcastMatchUpdated($match->id);

        $message = $innings->fresh()->status === 'completed'
            ? 'Delivery recorded. Innings completed.'
            : 'Delivery recorded successfully.';

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', $message);
    }

    public function undoLatest(GameMatch $match, Innings $innings): RedirectResponse
    {
        abort_unless($innings->match_id === $match->id, 404);

        $this->authorize('score', $match);

        if (! $this->deliveries->undoLastDelivery($match, $innings)) {
            return redirect()
                ->route('admin.matches.innings.score', [$match, $innings])
                ->with('error', 'There is no delivery to undo right now.');
        }

        $this->broadcastMatchUpdated($match->id);

        return redirect()
            ->route('admin.matches.innings.score', [$match, $innings])
            ->with('success', 'Last delivery undone successfully.');
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
