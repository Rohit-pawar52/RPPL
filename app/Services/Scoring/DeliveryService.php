<?php

namespace App\Services\Scoring;

use App\Models\Delivery;
use App\Models\DeliveryCorrection;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\ScoringEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ball-by-ball scoring engine. Delivery rows are the authoritative
 * historical record; Innings.total_runs/extras/legal_balls/total_wickets
 * are caches this service rebuilds by aggregating Delivery rows PLUS
 * certain ScoringEvent rows (penalty runs credited to the batting team,
 * a retired-out batter) — see recalculateInningsTotals(). Neither cache
 * is ever written independently of these two sources.
 *
 * S02 schema note: is_wide/is_no_ball are independent boolean flags, not
 * a single mutually-exclusive extra_type — a no-ball can legitimately
 * carry byes or leg-byes on the same delivery (bye_runs/leg_bye_runs
 * were already independent columns), which a single-select could never
 * represent. wide_runs is now always the fixed mandatory penalty (1)
 * when is_wide; wide_running_runs separately holds any runs physically
 * completed between the wickets on that wide.
 *
 * Ordering note: delivery_sequence (unique per innings, strictly
 * increasing, includes illegal deliveries) is the authoritative
 * chronological/ordering key. over_number/ball_number are a secondary,
 * purely presentational cricket-notation label derived from
 * legal_balls-so-far and frozen at write time — never recomputed for
 * existing rows.
 *
 * Current state (frozen S02 completion rules A-D): Innings.pending_state
 * is the server-authoritative record of who's on strike, who's the
 * non-striker, who's bowling, and whether ball entry must pause for a
 * new batter or a new over's bowler — see expectedBattingState(). It is
 * established once by InningsService::setUpOpeningState() (the explicit
 * Start Innings workflow — no Delivery is created merely by starting an
 * innings) and then kept continuously current by recordDelivery() after
 * every ball, and by ScoringEventService's non-delivery actions (Change
 * Strike, Retired Hurt/Out, Select New Batter, Select Over Bowler,
 * Change Bowler Mid-Over). Normal ball entry (StoreDeliveryRequest) no
 * longer submits striker/non-striker/bowler at all — recordDelivery()
 * derives them from this state when omitted; see
 * resolveImplicitParticipants(). Strike-rotation parity is based on
 * runs_off_bat + wide_running_runs + bye_runs + leg_bye_runs (never the
 * fixed wide/no-ball penalty runs), or runs_physically_run when a scorer
 * has explicitly recorded that the physically-completed run count
 * diverged from the credited total (a short run, or an unusual run-out).
 */
class DeliveryService
{
    /**
     * Whether a new delivery may currently be recorded: the innings
     * must belong to this match, be a valid innings number, both the
     * match and the innings must be live, and the overs limit (if any)
     * must not already be reached. This is the gate checked by the
     * controller before calling recordDelivery(), and rechecked again
     * inside recordDelivery()'s lock for the rare concurrent-request
     * case.
     */
    public function canRecordDelivery(GameMatch $match, Innings $innings): bool
    {
        return $this->isInningsScorable($match, $innings) && ! $this->isOverLimitReached($match, $innings);
    }

    public function isOverLimitReached(GameMatch $match, Innings $innings): bool
    {
        if (! $match->overs_per_innings) {
            return false;
        }

        return $innings->legal_balls >= $match->overs_per_innings * 6;
    }

    private function isInningsScorable(GameMatch $match, Innings $innings): bool
    {
        return (int) $innings->match_id === (int) $match->id
            && in_array($innings->innings_number, [1, 2], true)
            && $match->match_status === 'live'
            && $innings->status === 'live';
    }

    /**
     * A wide or no-ball does not count toward the bowler's 6-ball over;
     * everything else (a normal ball, or one scored as a bye/leg-bye,
     * including a no-ball's bye/leg-bye component) does.
     */
    public function determineLegality(bool $isWide, bool $isNoBall): bool
    {
        return ! ($isWide || $isNoBall);
    }

    /**
     * Maps the scorer's independent is_wide/is_no_ball/bye/leg-bye/
     * runs-off-bat facts onto the real columns. A wide's total is always
     * the fixed 1-run penalty (wide_runs) plus whatever was physically
     * run (wide_running_runs) — no bat runs or byes/leg-byes are
     * representable on a wide (real cricket: an unplayable ball that
     * reaches the boundary is scored entirely as wide runs, never
     * byes). A no-ball's mandatory single penalty run is fixed at 1,
     * with bat runs and/or byes/leg-byes recorded independently and
     * additively — never folded into no_ball_runs.
     *
     * @return array{runs_off_bat: int, wide_runs: int, wide_running_runs: int, no_ball_runs: int, bye_runs: int, leg_bye_runs: int}
     */
    public function calculateDeliveryRuns(bool $isWide, bool $isNoBall, int $runsOffBat, int $wideRunningRuns, int $byeRuns, int $legByeRuns): array
    {
        if ($isWide) {
            return ['runs_off_bat' => 0, 'wide_runs' => 1, 'wide_running_runs' => $wideRunningRuns, 'no_ball_runs' => 0, 'bye_runs' => 0, 'leg_bye_runs' => 0];
        }

        return [
            'runs_off_bat' => $runsOffBat,
            'wide_runs' => 0,
            'wide_running_runs' => 0,
            'no_ball_runs' => $isNoBall ? 1 : 0,
            'bye_runs' => $byeRuns,
            'leg_bye_runs' => $legByeRuns,
        ];
    }

    /**
     * A wide restricts dismissal to stumped/run_out. A no-ball, OR any
     * delivery bowled during an active Free Hit (frozen rule 3 — the
     * same restriction the law already applies to a no-ball itself),
     * restricts dismissal to run_out/obstructing_field only. Otherwise
     * every dismissal type this schema supports is available.
     *
     * @return list<string>
     */
    public function validWicketTypesForDelivery(bool $isWide, bool $isNoBall, bool $isFreeHit): array
    {
        if ($isWide) {
            return ['stumped', 'run_out'];
        }

        if ($isNoBall || $isFreeHit) {
            return ['run_out', 'obstructing_field'];
        }

        return Delivery::WICKET_TYPES;
    }

    /**
     * Caught's fielder is now optional (frozen rule 41) — fast wicket
     * entry must never be blocked purely because a fielder wasn't
     * recorded. Stumped remains the only dismissal type this schema
     * requires a fielder for.
     */
    public function dismissalRequiresFielder(string $wicketType): bool
    {
        return $wicketType === 'stumped';
    }

    public function dismissalForbidsFielder(string $wicketType): bool
    {
        return in_array($wicketType, ['bowled', 'lbw', 'hit_wicket'], true);
    }
    // run_out/obstructing_field/caught: fielder is optional — neither required nor forbidden.

    public function dismissalCountsAsWicket(string $wicketType): bool
    {
        return in_array($wicketType, Delivery::WICKET_TYPES, true);
    }

    /**
     * Whether the NEXT delivery of this innings is a Free Hit (frozen
     * rule 3): true when the latest delivery was a no-ball, or when the
     * latest delivery was itself bowled during an active Free Hit and
     * was illegal (wide/no-ball) — a Free Hit is only "used up" by a
     * legal delivery. Always server-computed at record time; never
     * client input.
     */
    public function isFreeHit(Innings $innings): bool
    {
        $latest = Delivery::query()
            ->where('innings_id', $innings->id)
            ->orderByDesc('delivery_sequence')
            ->first();

        if (! $latest) {
            return false;
        }

        return (bool) $latest->is_no_ball || ((bool) $latest->is_free_hit && (bool) $latest->is_wide);
    }

    /**
     * The bowler who bowled the last delivery of the over immediately
     * before the one about to start (frozen rule 9/22) — null if there
     * is no previous over yet. Uses the last row of that over_number
     * group rather than assuming one bowler per over, so a mid-over
     * change (frozen rule 23) is still correctly attributed to whoever
     * actually finished the over.
     */
    public function bowlerOfPreviousOver(Innings $innings): ?int
    {
        $currentOverNumber = intdiv($innings->legal_balls, 6);

        if ($currentOverNumber === 0) {
            return null;
        }

        $bowlerId = Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('over_number', $currentOverNumber - 1)
            ->orderByDesc('delivery_sequence')
            ->value('bowler_match_player_id');

        return $bowlerId !== null ? (int) $bowlerId : null;
    }

    /**
     * Records one delivery and rebuilds the innings' cached totals,
     * atomically. Locks the Innings row, rechecks every eligibility
     * condition against fresh data, determines delivery_sequence/
     * over_number/ball_number/is_free_hit from the locked row's current
     * state, then writes the Delivery and rebuilds the cache — all
     * inside one transaction.
     *
     * Normal ball entry (frozen S02 completion rule B) no longer submits
     * striker_match_player_id/non_striker_match_player_id/
     * bowler_match_player_id at all — whichever of these three keys is
     * missing (or explicitly null) is derived from the innings'
     * server-authoritative current state (expectedBattingState()) — the
     * scorer is never asked to re-tell the system facts it already
     * knows. A direct caller may still supply any of the three
     * explicitly (backward compatibility for existing direct-service
     * callers/tests, and defense-in-depth) — when supplied, the existing
     * full validation below still applies exactly as before.
     */
    public function recordDelivery(GameMatch $match, Innings $innings, array $data): Delivery
    {
        $data = $this->normalizeLegacyExtraShape($data);

        return DB::transaction(function () use ($match, $innings, $data) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            // Idempotency (frozen rule 51): a retried submission carrying
            // a key already recorded for THIS innings returns the
            // existing Delivery unchanged rather than creating a second
            // one — checked before any other validation/eligibility, so
            // a safe retry can never fail merely because the innings
            // moved on (over limit reached, innings completed) since the
            // original request actually succeeded.
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey) {
                $existing = Delivery::query()
                    ->where('innings_id', $lockedInnings->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            if (! $this->canRecordDelivery($match, $lockedInnings)) {
                throw ValidationException::withMessages([
                    'delivery' => __('This delivery cannot be recorded right now.'),
                ]);
            }

            $data = $this->resolveImplicitParticipants($lockedInnings, $data);

            $isWide = (bool) ($data['is_wide'] ?? false);
            $isNoBall = (bool) ($data['is_no_ball'] ?? false);
            $isFreeHit = $this->isFreeHit($lockedInnings);

            $this->assertParticipantsValid($match, $lockedInnings, $data, $isWide, $isNoBall, $isFreeHit);
            $this->assertExpectedBattingEnds($lockedInnings, $data);
            $this->assertNoConsecutiveOverBowler($lockedInnings, (int) $data['bowler_match_player_id']);

            $runs = $this->calculateDeliveryRuns(
                $isWide,
                $isNoBall,
                (int) ($data['runs_off_bat'] ?? 0),
                (int) ($data['wide_running_runs'] ?? 0),
                (int) ($data['bye_runs'] ?? 0),
                (int) ($data['leg_bye_runs'] ?? 0),
            );
            $isLegal = $this->determineLegality($isWide, $isNoBall);
            $isWicket = (bool) ($data['is_wicket'] ?? false);

            $delivery = Delivery::create([
                'innings_id' => $lockedInnings->id,
                'delivery_sequence' => Delivery::where('innings_id', $lockedInnings->id)->count() + 1,
                'over_number' => intdiv($lockedInnings->legal_balls, 6),
                'ball_number' => ($lockedInnings->legal_balls % 6) + 1,
                'striker_match_player_id' => $data['striker_match_player_id'],
                'non_striker_match_player_id' => $data['non_striker_match_player_id'],
                'bowler_match_player_id' => $data['bowler_match_player_id'],
                'runs_off_bat' => $runs['runs_off_bat'],
                'wide_runs' => $runs['wide_runs'],
                'wide_running_runs' => $runs['wide_running_runs'],
                'no_ball_runs' => $runs['no_ball_runs'],
                'bye_runs' => $runs['bye_runs'],
                'leg_bye_runs' => $runs['leg_bye_runs'],
                'penalty_runs' => 0,
                'total_runs' => array_sum($runs),
                'is_legal_delivery' => $isLegal,
                'is_free_hit' => $isFreeHit,
                'no_ball_reason' => $isNoBall ? ($data['no_ball_reason'] ?? null) : null,
                'is_wide' => $isWide,
                'is_no_ball' => $isNoBall,
                'is_wicket' => $isWicket,
                'is_short_run' => (bool) ($data['is_short_run'] ?? false),
                'runs_physically_run' => $data['runs_physically_run'] ?? null,
                'wicket_type' => $isWicket ? $data['wicket_type'] : null,
                'dismissed_match_player_id' => $isWicket ? $data['dismissed_match_player_id'] : null,
                'fielder_match_player_id' => $isWicket ? ($data['fielder_match_player_id'] ?? null) : null,
                'confirmed_survivor_end' => $isWicket ? ($data['confirmed_survivor_end'] ?? null) : null,
                'commentary' => $data['commentary'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'action_sequence' => $this->nextActionSequence($lockedInnings),
            ]);

            $this->recalculateInningsTotals($lockedInnings);

            $justCompleted = $this->hasReachedAutomaticCompletion($match, $lockedInnings);

            if ($justCompleted) {
                $lockedInnings->update(['status' => 'completed', 'completion_type' => 'automatic', 'completion_reason' => null]);
            }

            // pending_state is now always kept current as the canonical
            // "state for the next ball" (frozen S02 completion rules
            // A/B/D) — a recorded delivery is the freshest fact, so this
            // always overwrites whatever was there (a Change Strike/
            // retirement override, or the previous ball's state), rather
            // than merely clearing it as before. When this delivery just
            // ended the innings (10th wicket/all-out, overs exhausted, or
            // chase target reached), the computed "next ball" state is
            // stored as-is but never acted on: canRecordDelivery() and
            // every ScoringEventService action (selectNewBatter(),
            // selectOverBowler()) independently require the innings to
            // still be 'live' before doing anything with it (frozen S02
            // completion rule C.8/D — no batter/bowler prompt after the
            // innings ends).
            $lockedInnings->update(['pending_state' => $this->deriveNextStateFromDelivery($lockedInnings->fresh(), $delivery)]);

            return $delivery;
        });
    }

    /**
     * The next value for Delivery.action_sequence/ScoringEvent.
     * action_sequence — a single strictly-increasing ordering key shared
     * across both tables for this innings (frozen rule 42), so Universal
     * Undo can determine "the chronologically latest reversible action"
     * without relying on timestamp precision. Safe from races only
     * because every caller (recordDelivery() and every
     * ScoringEventService method) already computes this AFTER acquiring
     * the same Innings row lock this method requires as a parameter —
     * never call this against an unlocked Innings.
     */
    public function nextActionSequence(Innings $lockedInnings): int
    {
        $latestDelivery = (int) Delivery::where('innings_id', $lockedInnings->id)->max('action_sequence');
        $latestEvent = (int) ScoringEvent::where('innings_id', $lockedInnings->id)->max('action_sequence');

        return max($latestDelivery, $latestEvent) + 1;
    }

    /**
     * Fills in whichever of striker/non-striker/bowler the caller
     * omitted (frozen S02 completion rule B: normal ball entry no longer
     * submits them) from the innings' current server-authoritative
     * state. Missing/null striker+non-striker while the innings isn't
     * actually ready (not set up yet, or awaiting a new batter) and
     * missing/null bowler while awaiting a new over's bowler are both
     * rejected with a clear, specific message rather than falling
     * through to the generic participant-validation errors below.
     */
    private function resolveImplicitParticipants(Innings $innings, array $data): array
    {
        if (! isset($data['striker_match_player_id']) || ! isset($data['non_striker_match_player_id'])) {
            $state = $this->expectedBattingState($innings);

            if ($state['first_ball']) {
                throw ValidationException::withMessages([
                    'delivery' => __('This innings has not been set up yet — select the opening striker, non-striker, and bowler first.'),
                ]);
            }

            if ($state['requires_replacement']) {
                throw ValidationException::withMessages([
                    'delivery' => __('A new batter must be selected for the vacant end before the next delivery.'),
                ]);
            }

            $data['striker_match_player_id'] = $state['striker_id'];
            $data['non_striker_match_player_id'] = $state['non_striker_id'];
        }

        if (! isset($data['bowler_match_player_id'])) {
            $state ??= $this->expectedBattingState($innings);

            if ($state['awaiting_new_over_bowler'] || empty($state['bowler_id'])) {
                throw ValidationException::withMessages([
                    'delivery' => __('A bowler must be selected for this over before the next delivery.'),
                ]);
            }

            $data['bowler_match_player_id'] = $state['bowler_id'];
        }

        return $data;
    }

    /**
     * The state for the NEXT delivery of this innings, computed from the
     * delivery that was JUST recorded (or, in expectedBattingState()'s
     * fallback for an innings that predates pending_state, from whatever
     * the actual latest Delivery row is) — the single shared rotation-
     * math implementation both call sites use, so they can never drift
     * apart. $innings.legal_balls must already reflect this delivery
     * (i.e. called after recalculateInningsTotals()).
     *
     * @return array{first_ball: bool, requires_replacement: bool, striker_id: int|null, non_striker_id: int|null, survivor_id: int|null, survivor_end: string|null, bowler_id: int|null, awaiting_new_over_bowler: bool}
     */
    private function deriveNextStateFromDelivery(Innings $innings, Delivery $delivery): array
    {
        // Physically-run runs off the bat, on a wide, or as byes/leg-byes
        // all contribute to strike parity (frozen rule 10) — only the
        // fixed wide/no-ball penalty runs are excluded. runs_physically_run,
        // when the scorer explicitly recorded it (a short run, or an
        // unusual run-out), overrides the credited total for this purpose.
        $runningRuns = $delivery->runs_physically_run !== null
            ? (int) $delivery->runs_physically_run
            : $delivery->runs_off_bat + $delivery->wide_running_runs + $delivery->bye_runs + $delivery->leg_bye_runs;

        $swapForRuns = $runningRuns % 2 === 1;
        // This delivery completed an over (frozen S02 completion rule D):
        // a wide/no-ball is never legal, so it can never itself complete
        // one — the bowler for the new over must always be explicitly
        // selected via ScoringEventService::selectOverBowler().
        $swapForOver = $delivery->is_legal_delivery && $innings->legal_balls > 0 && $innings->legal_balls % 6 === 0;
        $swap = $swapForRuns !== $swapForOver;

        $preStriker = (int) $delivery->striker_match_player_id;
        $preNonStriker = (int) $delivery->non_striker_match_player_id;

        $nextStrikerSlot = $swap ? $preNonStriker : $preStriker;
        $nextNonStrikerSlot = $swap ? $preStriker : $preNonStriker;

        $bowlerId = (int) $delivery->bowler_match_player_id;
        $awaitingNewOverBowler = $swapForOver;

        if (! $delivery->is_wicket) {
            return [
                'first_ball' => false,
                'requires_replacement' => false,
                'striker_id' => $nextStrikerSlot,
                'non_striker_id' => $nextNonStrikerSlot,
                'survivor_id' => null,
                'survivor_end' => null,
                'bowler_id' => $awaitingNewOverBowler ? null : $bowlerId,
                'awaiting_new_over_bowler' => $awaitingNewOverBowler,
            ];
        }

        $dismissedId = (int) $delivery->dismissed_match_player_id;

        if ($dismissedId === $nextStrikerSlot) {
            $survivorId = $nextNonStrikerSlot;
            $survivorEnd = 'non_striker';
        } else {
            $survivorId = $nextStrikerSlot;
            $survivorEnd = 'striker';
        }

        // The scorer's explicit confirmation of the survivor's actual
        // end (frozen rule 19) overrides the computed slot when given.
        if ($delivery->confirmed_survivor_end !== null) {
            $survivorEnd = $delivery->confirmed_survivor_end;
        }

        return [
            'first_ball' => false,
            'requires_replacement' => true,
            'striker_id' => null,
            'non_striker_id' => null,
            'survivor_id' => $survivorId,
            'survivor_end' => $survivorEnd,
            'bowler_id' => $awaitingNewOverBowler ? null : $bowlerId,
            'awaiting_new_over_bowler' => $awaitingNewOverBowler,
        ];
    }

    /**
     * Backward-compatibility shim for direct (non-HTTP) callers still
     * passing the pre-S02 single-select extra_type/extra_amount shape
     * (e.g. existing tests exercising the plain, single-extra-category
     * case) — translates it into the new independent is_wide/is_no_ball/
     * bye_runs/leg_bye_runs/wide_running_runs fields. StoreDeliveryRequest
     * has the same shim for the HTTP path. A caller already using the
     * new fields is completely unaffected — this only fires when
     * extra_type is actually present in $data.
     */
    private function normalizeLegacyExtraShape(array $data): array
    {
        if (! array_key_exists('extra_type', $data)) {
            return $data;
        }

        $extraAmount = (int) ($data['extra_amount'] ?? 0);

        return match ($data['extra_type']) {
            'wide' => array_merge($data, ['is_wide' => true, 'wide_running_runs' => max(0, $extraAmount - 1)]),
            'no_ball' => array_merge($data, ['is_no_ball' => true]),
            'bye' => array_merge($data, ['bye_runs' => $extraAmount]),
            'leg_bye' => array_merge($data, ['leg_bye_runs' => $extraAmount]),
            default => $data,
        };
    }

    /**
     * The same bowler must not bowl two overs in a row (frozen rule 9),
     * checked only at the exact boundary where a new over is about to
     * start (legal_balls is a clean multiple of 6) — a wide/no-ball
     * retried at that same boundary is still checked against the same
     * previous-over bowler, since the over hasn't actually started yet.
     */
    private function assertNoConsecutiveOverBowler(Innings $innings, int $bowlerMatchPlayerId): void
    {
        if ($innings->legal_balls % 6 !== 0) {
            return;
        }

        $previousBowlerId = $this->bowlerOfPreviousOver($innings);

        if ($previousBowlerId !== null && $previousBowlerId === $bowlerMatchPlayerId) {
            throw ValidationException::withMessages([
                'bowler_match_player_id' => __('The same bowler cannot bowl two overs in a row.'),
            ]);
        }
    }

    /**
     * The three, and only three, objective conditions that end an
     * innings automatically: all out, the match's configured legal-ball
     * limit reached, or — second innings only — the chase target (first
     * innings runs + 1) reached. total_wickets/total_runs/legal_balls
     * already include any ScoringEvent contributions (retired-out,
     * penalty runs) via recalculateInningsTotals(), so a retirement can
     * itself trigger all-out here exactly like a Delivery wicket would.
     */
    public function hasReachedAutomaticCompletion(GameMatch $match, Innings $innings): bool
    {
        if ($innings->total_wickets >= Innings::MAX_WICKETS) {
            return true;
        }

        if ($match->overs_per_innings && $innings->legal_balls >= $match->overs_per_innings * 6) {
            return true;
        }

        if ((int) $innings->innings_number === 2) {
            $firstInnings = Innings::query()
                ->where('match_id', $match->id)
                ->where('innings_number', 1)
                ->first();

            if ($firstInnings && $innings->total_runs >= $firstInnings->total_runs + 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes ONLY the most recent Delivery (by delivery_sequence) of an
     * undoable innings and rebuilds the cache from what remains. Still
     * deliberately the only correction mechanism for a Delivery itself —
     * no generic Delivery edit/delete.
     *
     * An innings that is already 'completed' may still be undone, but
     * ONLY when innings.completion_type is 'automatic' — a manually
     * completed innings (frozen rule 15/16) is never reopened by undo;
     * reopening one requires the explicit, reasoned
     * InningsService::reopenInnings() action instead. completion_type is
     * a genuine stored fact (backfilled for pre-existing data), not a
     * re-derived guess.
     */
    public function undoLastDelivery(GameMatch $match, Innings $innings): bool
    {
        return DB::transaction(function () use ($match, $innings) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->isInningsUndoable($match, $lockedInnings)) {
                return false;
            }

            $latest = Delivery::query()
                ->where('innings_id', $lockedInnings->id)
                ->orderByDesc('delivery_sequence')
                ->lockForUpdate()
                ->first();

            if (! $latest) {
                return false;
            }

            $wasCompleted = $lockedInnings->status === 'completed';

            $latest->delete();

            $this->recalculateInningsTotals($lockedInnings);

            if ($wasCompleted && ! $this->hasReachedAutomaticCompletion($match, $lockedInnings)) {
                $lockedInnings->update(['status' => 'live', 'completion_type' => null, 'completion_reason' => null]);
            }

            // pending_state was written for the ball that just got
            // undone — it must never linger, or the next delivery/action
            // would see a state one ball ahead of reality. Clearing it
            // lets expectedBattingState() naturally re-derive from
            // whatever is now the latest Delivery (or "first ball" if
            // none remain) — the same "undo naturally restores the
            // correct expected state" guarantee this method has always
            // had, extended to the persisted-state model.
            $lockedInnings->update(['pending_state' => null]);

            return true;
        });
    }

    /**
     * Shared eligibility gate for every reversal/correction action this
     * service and UndoService expose (undo, Universal Undo, quick
     * correction): the match must still be live, and the innings must
     * either still be live or be 'completed' with completion_type
     * 'automatic' — a manually completed innings (frozen rule 15/16) is
     * never touched by any of these, only by the explicit, reasoned
     * InningsService::reopenInnings() action.
     */
    public function isInningsUndoable(GameMatch $match, Innings $innings): bool
    {
        if ((int) $innings->match_id !== (int) $match->id
            || ! in_array($innings->innings_number, [1, 2], true)
            || $match->match_status !== 'live') {
            return false;
        }

        if ($innings->status === 'live') {
            return true;
        }

        return $innings->status === 'completed' && $innings->completion_type === 'automatic';
    }

    /**
     * Frozen rules 43/44/46: only the latest 3 recorded Delivery rows of
     * an innings are ever open to quick correction — never an arbitrary
     * older ball (that is explicitly deferred, frozen rule 45).
     */
    public const CORRECTION_WINDOW = 3;

    /**
     * @return Collection<int, Delivery>
     */
    public function correctableDeliveries(Innings $innings): Collection
    {
        return Delivery::query()
            ->where('innings_id', $innings->id)
            ->orderByDesc('delivery_sequence')
            ->limit(self::CORRECTION_WINDOW)
            ->get();
    }

    /**
     * Edits one of the latest 3 deliveries of an innings in place (frozen
     * rules 43/44/46) — the delivery keeps its identity (delivery_sequence/
     * over_number/ball_number, and its striker/non-striker/bowler, which
     * correction never changes; that is what Change Strike/Change Bowler
     * Mid-Over are for), only its recorded OUTCOME facts (runs, extras,
     * wicket information, physical-run fields, commentary) are replaced.
     * Every correction is audited via DeliveryCorrection (old/new full
     * fact snapshot, who, when, optional reason) — see that model's
     * migration docblock — and the delivery's own is_edited/edit_reason
     * flags are set for at-a-glance display.
     *
     * Two independent safety gates, both required:
     *   1. Wide/No Ball state can only change on the innings' current
     *      LATEST delivery — changing it on an older one would silently
     *      invalidate the legality/over-boundary numbering and Free Hit
     *      status of every delivery recorded after it (frozen rule 45's
     *      "do not implement deep correction" boundary).
     *   2. For anything OTHER than the latest delivery, the correction is
     *      rejected outright if it would change what
     *      deriveNextStateFromDelivery() computes for it (see
     *      wouldChangeDownstreamState()) — i.e. if a later, already-
     *      recorded delivery's striker/non-striker/bowler/replacement
     *      expectation was built on a fact this correction would now
     *      contradict. The LATEST delivery has no such restriction: its
     *      pending_state is simply recomputed fresh afterward, exactly as
     *      if it had been recorded correctly the first time.
     *
     * Runs/extras/wicket totals always rebuild via
     * recalculateInningsTotals() regardless of position in the window,
     * and the innings' automatic-completion state (frozen rule: "result/
     * completion state must not remain knowingly stale") is re-evaluated
     * afterward in both directions — a correction can newly reach
     * completion (e.g. correcting in the 10th wicket) or newly fall
     * short of it (e.g. correcting away the wicket/runs that had reached
     * it), exactly like recordDelivery()/undoLastDelivery() already do.
     *
     * @param  array<string, mixed>  $newData
     */
    public function correctDelivery(GameMatch $match, Innings $innings, Delivery $delivery, array $newData, ?string $reason, User $performedBy): Delivery
    {
        $newData = $this->normalizeLegacyExtraShape($newData);

        return DB::transaction(function () use ($match, $innings, $delivery, $newData, $reason, $performedBy) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();
            $lockedDelivery = Delivery::query()->whereKey($delivery->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedDelivery->innings_id !== (int) $lockedInnings->id) {
                throw ValidationException::withMessages(['delivery' => __('This delivery does not belong to this innings.')]);
            }

            if (! $this->isInningsUndoable($match, $lockedInnings)) {
                throw ValidationException::withMessages(['delivery' => __('This innings can no longer be corrected.')]);
            }

            $window = $this->correctableDeliveries($lockedInnings);

            if (! $window->contains('id', $lockedDelivery->id)) {
                throw ValidationException::withMessages([
                    'delivery' => __('Only the latest :count deliveries of this innings can be corrected.', ['count' => self::CORRECTION_WINDOW]),
                ]);
            }

            $isLatest = (int) $window->first()->id === (int) $lockedDelivery->id;

            $isWide = array_key_exists('is_wide', $newData) ? (bool) $newData['is_wide'] : (bool) $lockedDelivery->is_wide;
            $isNoBall = array_key_exists('is_no_ball', $newData) ? (bool) $newData['is_no_ball'] : (bool) $lockedDelivery->is_no_ball;

            if (! $isLatest && ($isWide !== (bool) $lockedDelivery->is_wide || $isNoBall !== (bool) $lockedDelivery->is_no_ball)) {
                throw ValidationException::withMessages([
                    'delivery' => __('Wide/No Ball can only be changed on the most recent delivery — changing it here would invalidate the legality and Free Hit sequencing of deliveries already recorded after it.'),
                ]);
            }

            // is_free_hit is an immutable fact of when THIS delivery was
            // bowled (it depends only on the delivery before it, which a
            // correction never touches) — reused as stored, never
            // re-derived from the correction itself.
            $isFreeHit = (bool) $lockedDelivery->is_free_hit;

            $runs = $this->calculateDeliveryRuns(
                $isWide,
                $isNoBall,
                (int) ($newData['runs_off_bat'] ?? $lockedDelivery->runs_off_bat),
                (int) ($newData['wide_running_runs'] ?? $lockedDelivery->wide_running_runs),
                (int) ($newData['bye_runs'] ?? $lockedDelivery->bye_runs),
                (int) ($newData['leg_bye_runs'] ?? $lockedDelivery->leg_bye_runs),
            );

            $isWicket = array_key_exists('is_wicket', $newData) ? (bool) $newData['is_wicket'] : (bool) $lockedDelivery->is_wicket;
            $wicketType = $isWicket ? ($newData['wicket_type'] ?? $lockedDelivery->wicket_type) : null;
            $dismissedId = $isWicket ? (int) ($newData['dismissed_match_player_id'] ?? $lockedDelivery->dismissed_match_player_id) : null;
            $fielderId = $isWicket ? ($newData['fielder_match_player_id'] ?? $lockedDelivery->fielder_match_player_id) : null;
            $confirmedSurvivorEnd = $isWicket ? ($newData['confirmed_survivor_end'] ?? $lockedDelivery->confirmed_survivor_end) : null;

            $this->assertParticipantsValid($match, $lockedInnings, [
                'striker_match_player_id' => $lockedDelivery->striker_match_player_id,
                'non_striker_match_player_id' => $lockedDelivery->non_striker_match_player_id,
                'bowler_match_player_id' => $lockedDelivery->bowler_match_player_id,
                'is_wicket' => $isWicket,
                'wicket_type' => $wicketType,
                'dismissed_match_player_id' => $dismissedId,
                'fielder_match_player_id' => $fielderId,
            ], $isWide, $isNoBall, $isFreeHit);

            $newAttributes = [
                'runs_off_bat' => $runs['runs_off_bat'],
                'wide_runs' => $runs['wide_runs'],
                'wide_running_runs' => $runs['wide_running_runs'],
                'no_ball_runs' => $runs['no_ball_runs'],
                'bye_runs' => $runs['bye_runs'],
                'leg_bye_runs' => $runs['leg_bye_runs'],
                'total_runs' => array_sum($runs),
                'is_legal_delivery' => $this->determineLegality($isWide, $isNoBall),
                'no_ball_reason' => $isNoBall ? ($newData['no_ball_reason'] ?? $lockedDelivery->no_ball_reason) : null,
                'is_wide' => $isWide,
                'is_no_ball' => $isNoBall,
                'is_wicket' => $isWicket,
                'is_short_run' => array_key_exists('is_short_run', $newData) ? (bool) $newData['is_short_run'] : (bool) $lockedDelivery->is_short_run,
                'runs_physically_run' => array_key_exists('runs_physically_run', $newData) ? $newData['runs_physically_run'] : $lockedDelivery->runs_physically_run,
                'wicket_type' => $wicketType,
                'dismissed_match_player_id' => $dismissedId,
                'fielder_match_player_id' => $fielderId,
                'confirmed_survivor_end' => $confirmedSurvivorEnd,
                'commentary' => array_key_exists('commentary', $newData) ? $newData['commentary'] : $lockedDelivery->commentary,
            ];

            if (! $isLatest && $this->wouldChangeDownstreamState($lockedDelivery, $newAttributes)) {
                throw ValidationException::withMessages([
                    'delivery' => __('This correction would change what a later delivery already assumed (who was on strike, who was due to bat next, or the bowler for the next over) — only the most recent delivery can carry that kind of change.'),
                ]);
            }

            $oldValues = $lockedDelivery->only(array_keys($newAttributes));

            $lockedDelivery->update($newAttributes + ['is_edited' => true, 'edit_reason' => $reason]);

            DeliveryCorrection::create([
                'delivery_id' => $lockedDelivery->id,
                'innings_id' => $lockedInnings->id,
                'match_id' => $match->id,
                'old_values' => $oldValues,
                'new_values' => $newAttributes,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
            ]);

            $this->recalculateInningsTotals($lockedInnings);

            if ($isLatest) {
                $lockedInnings->update([
                    'pending_state' => $this->deriveNextStateFromDelivery($lockedInnings->fresh(), $lockedDelivery->fresh()),
                ]);
            }

            $this->reconcileCompletionState($match, $lockedInnings);

            return $lockedDelivery->fresh();
        });
    }

    /**
     * Re-evaluates automatic completion after any write that can change
     * total_wickets/total_runs/legal_balls without going through
     * recordDelivery() itself (a quick correction, or Universal Undo
     * reversing a retired-out/penalty-runs ScoringEvent): a correction
     * can newly REACH completion (e.g. correcting in the 10th wicket) or
     * newly fall short of it (e.g. correcting away the wicket/runs that
     * had reached it) — "result/completion state must not remain
     * knowingly stale" applies in both directions, exactly like
     * recordDelivery()/undoLastDelivery() already guarantee for a normal
     * ball. Only ever toggles an 'automatic' completion — a manually
     * completed innings is untouched, exactly like undoLastDelivery().
     */
    public function reconcileCompletionState(GameMatch $match, Innings $innings): void
    {
        $fresh = $innings->fresh();
        $nowComplete = $this->hasReachedAutomaticCompletion($match, $fresh);

        if ($fresh->status === 'live' && $nowComplete) {
            $fresh->update(['status' => 'completed', 'completion_type' => 'automatic', 'completion_reason' => null]);
        } elseif ($fresh->status === 'completed' && $fresh->completion_type === 'automatic' && ! $nowComplete) {
            $fresh->update(['status' => 'live', 'completion_type' => null, 'completion_reason' => null]);
        }
    }

    /**
     * The generic safety check behind correctDelivery()'s rule 2 (see its
     * docblock): recomputes deriveNextStateFromDelivery() for the OLD and
     * the proposed NEW fact set of the same delivery, using its own
     * unchanged over_number/ball_number to reconstruct "legal balls so
     * far as of this delivery" — never the innings' current legal_balls,
     * which reflects the whole innings, not just up to this ball. Equal
     * results mean nothing downstream needs to change; different results
     * mean an already-recorded later delivery relied on a fact this
     * correction would now contradict.
     *
     * @param  array<string, mixed>  $newAttributes
     */
    private function wouldChangeDownstreamState(Delivery $original, array $newAttributes): bool
    {
        $legalBallsBeforeThisBall = $original->over_number * 6 + ($original->ball_number - 1);

        $oldState = $this->deriveNextStateFromDelivery(
            new Innings(['legal_balls' => $legalBallsBeforeThisBall + ($original->is_legal_delivery ? 1 : 0)]),
            $original,
        );

        $candidate = $original->replicate();
        $candidate->forceFill($newAttributes);

        $newState = $this->deriveNextStateFromDelivery(
            new Innings(['legal_balls' => $legalBallsBeforeThisBall + ($newAttributes['is_legal_delivery'] ? 1 : 0)]),
            $candidate,
        );

        return $oldState !== $newState;
    }

    /**
     * The canonical cache-rebuild mechanism: aggregates from Delivery
     * rows (as before) PLUS two ScoringEvent contributions that are
     * deliberately never Delivery rows themselves (frozen rules 4/5/6):
     * a retired-out batter counts as a wicket, and penalty runs awarded
     * to THIS innings' batting team count as runs and extras (as Penalty
     * extras — never credited to a batter or charged to a bowler).
     *
     * Penalty credit is looked up by MATCH + the awarded team, not by
     * which innings the award happened to be recorded from — a penalty
     * awarded to a team that hasn't batted yet in this match is still
     * found here the moment that team's own Innings row exists, because
     * this query re-reads the same ScoringEvent rows fresh every time
     * (see ScoringEventService::awardPenaltyRuns()'s docblock for the
     * full accounting rationale). This is also why recalculating
     * multiple times is safe: it is always a full re-aggregation from
     * the one authoritative ScoringEvent/Delivery source, never an
     * incremental add, so it can never double-credit the same award.
     *
     * Neither ScoringEvent type here ever touches legal_balls or strike
     * state.
     */
    public function recalculateInningsTotals(Innings $innings): void
    {
        $totals = Delivery::query()
            ->where('innings_id', $innings->id)
            ->selectRaw('
                COALESCE(SUM(total_runs), 0) as total_runs,
                COALESCE(SUM(wide_runs + wide_running_runs + no_ball_runs + bye_runs + leg_bye_runs + penalty_runs), 0) as extras,
                COALESCE(SUM(CASE WHEN is_legal_delivery THEN 1 ELSE 0 END), 0) as legal_balls,
                COALESCE(SUM(CASE WHEN is_wicket THEN 1 ELSE 0 END), 0) as total_wickets
            ')
            ->first();

        $penaltyRunsForBattingTeam = (int) ScoringEvent::query()
            ->where('match_id', $innings->match_id)
            ->where('type', ScoringEvent::TYPE_PENALTY_RUNS)
            ->where('awarded_team_id', $innings->batting_team_id)
            ->notUndone()
            ->sum('runs');

        $retiredOutCount = ScoringEvent::query()
            ->where('innings_id', $innings->id)
            ->where('type', ScoringEvent::TYPE_RETIRED_OUT)
            ->notUndone()
            ->count();

        $innings->update([
            'total_runs' => (int) $totals->total_runs + $penaltyRunsForBattingTeam,
            'extras' => (int) $totals->extras + $penaltyRunsForBattingTeam,
            'legal_balls' => (int) $totals->legal_balls,
            'total_wickets' => (int) $totals->total_wickets + $retiredOutCount,
        ]);
    }

    /**
     * The innings' current server-authoritative state — canonical source
     * for who's on strike, who's the non-striker, who's bowling, and
     * whether ball entry must be interrupted for a new batter or a new
     * over's bowler (frozen S02 completion rules A/B/C/D). Checks
     * Innings.pending_state FIRST: for any innings that has gone through
     * the explicit Start Innings setup (InningsService::
     * setUpOpeningState()), or has ever had recordDelivery()/a
     * ScoringEventService action run against it, this is always
     * populated and is the single source of truth, kept continuously
     * current rather than recomputed on every read.
     *
     * Falls back to deriving from the latest Delivery row only for an
     * innings that predates this mechanism (pending_state still null but
     * Delivery rows already exist) — the exact same rotation math
     * (deriveNextStateFromDelivery()) a fresh delivery would have
     * written, so the two paths can never disagree. An innings with
     * neither a pending_state nor any Delivery yet is genuinely awaiting
     * its opening setup.
     *
     * Also used by ScoringController to decide which screen (setup / new
     * batter / new bowler / normal ball entry) to render, and to display
     * the current striker/non-striker/bowler read-only.
     *
     * @return array{first_ball: bool, requires_replacement: bool, striker_id: int|null, non_striker_id: int|null, survivor_id: int|null, survivor_end: string|null, bowler_id: int|null, awaiting_new_over_bowler: bool}
     */
    public function expectedBattingState(Innings $innings): array
    {
        if ($innings->pending_state !== null) {
            return $innings->pending_state;
        }

        $latest = Delivery::query()
            ->where('innings_id', $innings->id)
            ->orderByDesc('delivery_sequence')
            ->first();

        if (! $latest) {
            return [
                'first_ball' => true,
                'requires_replacement' => false,
                'striker_id' => null,
                'non_striker_id' => null,
                'survivor_id' => null,
                'survivor_end' => null,
                'bowler_id' => null,
                'awaiting_new_over_bowler' => false,
            ];
        }

        return $this->deriveNextStateFromDelivery($innings, $latest);
    }

    /**
     * Every match_player who can no longer bat in this innings: given
     * out by a Delivery, or recorded as Retired Out (frozen rule 5 —
     * counts as a wicket, cannot return). A Retired Hurt batter (frozen
     * rule 4) is deliberately NOT included here — they remain eligible
     * to be selected again later as the new batter for a vacant end.
     *
     * @return list<int>
     */
    public function dismissedMatchPlayerIds(Innings $innings): array
    {
        $deliveryDismissed = Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('is_wicket', true)
            ->pluck('dismissed_match_player_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $retiredOut = ScoringEvent::query()
            ->where('innings_id', $innings->id)
            ->where('type', ScoringEvent::TYPE_RETIRED_OUT)
            ->notUndone()
            ->pluck('match_player_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($deliveryDismissed, $retiredOut)));
    }

    /**
     * Enforces the strike-rotation rules against the submitted striker/
     * non-striker: the first delivery of an innings is a free choice,
     * every delivery after that must supply exactly the pair
     * expectedBattingState() derives (or, after a wicket/retirement, the
     * correct surviving batter at their correct end plus any new,
     * currently-eligible batter for the vacant end).
     */
    private function assertExpectedBattingEnds(Innings $innings, array $data): void
    {
        $submittedStriker = (int) $data['striker_match_player_id'];
        $submittedNonStriker = (int) $data['non_striker_match_player_id'];

        $dismissed = $this->dismissedMatchPlayerIds($innings);

        if (in_array($submittedStriker, $dismissed, true) || in_array($submittedNonStriker, $dismissed, true)) {
            throw ValidationException::withMessages([
                'striker_match_player_id' => __('A player already dismissed or retired out in this innings cannot return to the crease.'),
            ]);
        }

        $state = $this->expectedBattingState($innings);

        if ($state['first_ball']) {
            return;
        }

        if ($state['requires_replacement']) {
            $survivorSubmittedCorrectly = $state['survivor_end'] === 'striker'
                ? $submittedStriker === $state['survivor_id']
                : $submittedNonStriker === $state['survivor_id'];

            if (! $survivorSubmittedCorrectly) {
                throw ValidationException::withMessages([
                    'striker_match_player_id' => __('Selected striker/non-striker do not match the expected batting ends.'),
                ]);
            }

            $newBatterId = $state['survivor_end'] === 'striker' ? $submittedNonStriker : $submittedStriker;

            if ($newBatterId === $state['survivor_id']) {
                throw ValidationException::withMessages([
                    'striker_match_player_id' => __('The replacement batter must be a new player, not the surviving batter.'),
                ]);
            }

            return;
        }

        if ($submittedStriker !== $state['striker_id'] || $submittedNonStriker !== $state['non_striker_id']) {
            throw ValidationException::withMessages([
                'striker_match_player_id' => __('Selected striker/non-striker do not match the expected batting ends.'),
            ]);
        }
    }

    /**
     * Re-verifies, against fresh data, the core eligibility rules
     * StoreDeliveryRequest already validated — the same defense-in-
     * depth pattern used throughout this project.
     */
    private function assertParticipantsValid(GameMatch $match, Innings $innings, array $data, bool $isWide, bool $isNoBall, bool $isFreeHit): void
    {
        if (! $this->matchPlayerBelongsToTeam($data['striker_match_player_id'], $match, $innings->batting_team_id)) {
            throw ValidationException::withMessages(['striker_match_player_id' => __('The striker must be a selected player from the batting team.')]);
        }

        if (! $this->matchPlayerBelongsToTeam($data['non_striker_match_player_id'], $match, $innings->batting_team_id)) {
            throw ValidationException::withMessages(['non_striker_match_player_id' => __('The non-striker must be a selected player from the batting team.')]);
        }

        if ((int) $data['striker_match_player_id'] === (int) $data['non_striker_match_player_id']) {
            throw ValidationException::withMessages(['non_striker_match_player_id' => __('The striker and non-striker must be different players.')]);
        }

        if (! $this->matchPlayerBelongsToTeam($data['bowler_match_player_id'], $match, $innings->bowling_team_id)) {
            throw ValidationException::withMessages(['bowler_match_player_id' => __('The bowler must be a selected player from the bowling team.')]);
        }

        if (empty($data['is_wicket'])) {
            return;
        }

        $dismissedId = (int) ($data['dismissed_match_player_id'] ?? 0);

        if (! in_array($dismissedId, [(int) $data['striker_match_player_id'], (int) $data['non_striker_match_player_id']], true)) {
            throw ValidationException::withMessages(['dismissed_match_player_id' => __('The dismissed player must be the striker or non-striker on this delivery.')]);
        }

        $wicketType = $data['wicket_type'] ?? null;

        if (! $wicketType || ! in_array($wicketType, $this->validWicketTypesForDelivery($isWide, $isNoBall, $isFreeHit), true)) {
            throw ValidationException::withMessages(['wicket_type' => __('This dismissal type is not valid for this kind of delivery.')]);
        }

        $fielderId = $data['fielder_match_player_id'] ?? null;

        if ($this->dismissalRequiresFielder($wicketType) && ! $fielderId) {
            throw ValidationException::withMessages(['fielder_match_player_id' => __('A fielder is required for this dismissal type.')]);
        }

        if ($this->dismissalForbidsFielder($wicketType) && $fielderId) {
            throw ValidationException::withMessages(['fielder_match_player_id' => __('A fielder must not be recorded for this dismissal type.')]);
        }

        if ($fielderId && ! $this->matchPlayerBelongsToTeam($fielderId, $match, $innings->bowling_team_id)) {
            throw ValidationException::withMessages(['fielder_match_player_id' => __('The fielder must be a selected player from the bowling team.')]);
        }
    }

    public function matchPlayerBelongsToTeam(int $matchPlayerId, GameMatch $match, int $editionTeamId): bool
    {
        return MatchPlayer::query()
            ->where('id', $matchPlayerId)
            ->where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeamId))
            ->exists();
    }
}
