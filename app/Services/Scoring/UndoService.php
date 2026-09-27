<?php

namespace App\Services\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\ScoringEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Universal Undo (frozen rule 42): reverses whichever reversible scoring
 * action — a Delivery, or one of ScoringEvent::UNDOABLE_TYPES — is
 * chronologically latest for this innings, determined by
 * action_sequence (a single ordering key both tables share; see
 * DeliveryService::nextActionSequence()'s docblock for why plain
 * timestamps are not precise enough on a fast match-day UI).
 *
 * Deliberately does NOT cover: a quick correction (DeliveryService::
 * correctDelivery() — corrections are re-corrected, never "undone", a
 * distinct rule 43/44/46 feature), or any lifecycle ScoringEvent (manual
 * innings completion, innings/match reopen, Super Over result) — those
 * already have their own explicit, dedicated reopen/re-record actions
 * and are intentionally excluded from ScoringEvent::UNDOABLE_TYPES.
 */
class UndoService
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    /**
     * @return array{undone: bool, type: string|null, message: string}
     */
    public function undoLastAction(GameMatch $match, Innings $innings, User $performedBy): array
    {
        return DB::transaction(function () use ($match, $innings, $performedBy) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->deliveries->isInningsUndoable($match, $lockedInnings)) {
                return ['undone' => false, 'type' => null, 'message' => 'This innings can no longer be corrected.'];
            }

            $latestDelivery = Delivery::query()
                ->where('innings_id', $lockedInnings->id)
                ->orderByDesc('action_sequence')
                ->orderByDesc('delivery_sequence')
                ->first();

            $latestEvent = ScoringEvent::query()
                ->where('innings_id', $lockedInnings->id)
                ->whereIn('type', ScoringEvent::UNDOABLE_TYPES)
                ->notUndone()
                ->orderByDesc('action_sequence')
                ->orderByDesc('id')
                ->first();

            if (! $latestDelivery && ! $latestEvent) {
                return ['undone' => false, 'type' => null, 'message' => 'There is nothing to undo right now.'];
            }

            $deliverySequence = $latestDelivery?->action_sequence ?? -1;
            $eventSequence = $latestEvent?->action_sequence ?? -1;

            if ($deliverySequence >= $eventSequence) {
                $ok = $this->deliveries->undoLastDelivery($match, $lockedInnings);

                return [
                    'undone' => $ok,
                    'type' => 'delivery',
                    'message' => $ok ? 'Last delivery undone successfully.' : 'There is no delivery to undo right now.',
                ];
            }

            return $this->undoScoringEvent($match, $lockedInnings, $latestEvent, $performedBy);
        });
    }

    /**
     * @return array{undone: bool, type: string|null, message: string}
     */
    private function undoScoringEvent(GameMatch $match, Innings $lockedInnings, ScoringEvent $event, User $performedBy): array
    {
        // Penalty Runs never touches pending_state (frozen rule 6 — a
        // pure runs/extras credit, never a batting-end/bowler change), so
        // it has no "before" snapshot to restore; every other undoable
        // type does.
        $affectsPendingState = $event->type !== ScoringEvent::TYPE_PENALTY_RUNS;
        $beforeState = $event->payload['before_pending_state'] ?? null;

        // Defensive only — every pending-state-affecting event type in
        // ScoringEvent::UNDOABLE_TYPES has always stored this snapshot
        // since it was introduced (frozen rule 42.4/42.5: never destroy
        // audit history, always know what was undone). If it is somehow
        // missing, refusing is the safe failure mode rule 42.8 asks for,
        // never a guessed reconstruction.
        if ($affectsPendingState && ! is_array($beforeState)) {
            return ['undone' => false, 'type' => $event->type, 'message' => 'This action cannot be safely undone.'];
        }

        $event->update(['undone_at' => now(), 'undone_by' => $performedBy->id]);

        if ($affectsPendingState) {
            $lockedInnings->update(['pending_state' => $beforeState]);
        }

        // Retired Out counts as a wicket, and Penalty Runs counts toward
        // the awarded team's total, ONLY via a live (non-undone)
        // ScoringEvent row — recalculateInningsTotals() re-aggregates
        // fresh every time, so simply excluding this now-undone row (via
        // ScoringEvent::notUndone()) is enough to reverse its effect; see
        // that method's docblock.
        if ($event->type === ScoringEvent::TYPE_RETIRED_OUT) {
            $this->deliveries->recalculateInningsTotals($lockedInnings);
            $this->deliveries->reconcileCompletionState($match, $lockedInnings);
        } elseif ($event->type === ScoringEvent::TYPE_PENALTY_RUNS) {
            $targetInnings = Innings::query()
                ->where('match_id', $match->id)
                ->where('batting_team_id', $event->awarded_team_id)
                ->lockForUpdate()
                ->first();

            if ($targetInnings) {
                $this->deliveries->recalculateInningsTotals($targetInnings);
                $this->deliveries->reconcileCompletionState($match, $targetInnings);
            }
        }

        return [
            'undone' => true,
            'type' => $event->type,
            'message' => $this->undoMessageFor($event->type),
        ];
    }

    private function undoMessageFor(string $type): string
    {
        return match ($type) {
            ScoringEvent::TYPE_CHANGE_STRIKE => 'Strike correction undone.',
            ScoringEvent::TYPE_RETIRED_HURT => 'Retired hurt undone.',
            ScoringEvent::TYPE_RETIRED_OUT => 'Retired out undone.',
            ScoringEvent::TYPE_BOWLER_CHANGE_MID_OVER => 'Mid-over bowler change undone.',
            ScoringEvent::TYPE_NEW_BATTER_SELECTED => 'New batter selection undone.',
            ScoringEvent::TYPE_OVER_BOWLER_SELECTED => 'Over bowler selection undone.',
            ScoringEvent::TYPE_PENALTY_RUNS => 'Penalty runs award undone.',
            default => 'Last action undone.',
        };
    }
}
