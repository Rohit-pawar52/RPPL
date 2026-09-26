<?php

namespace App\Services\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\ScoringEvent;
use Illuminate\Support\Facades\Auth;
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
 * Strike rotation: the expected striker/non-striker for the NEXT
 * delivery is normally derived entirely from the latest Delivery row
 * (see expectedBattingState()) — except when Innings.pending_state
 * holds an explicit override written by a non-delivery scoring event
 * (Change Strike, Retired Hurt/Out — see ScoringEventService), which
 * takes precedence until the next Delivery is recorded and clears it.
 * Strike-rotation parity is based on runs_off_bat + wide_running_runs +
 * bye_runs + leg_bye_runs (never the fixed wide/no-ball penalty runs),
 * or runs_physically_run when a scorer has explicitly recorded that the
 * physically-completed run count diverged from the credited total (a
 * short run, or an unusual run-out).
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
     */
    public function recordDelivery(GameMatch $match, Innings $innings, array $data): Delivery
    {
        $data = $this->normalizeLegacyExtraShape($data);

        return DB::transaction(function () use ($match, $innings, $data) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->canRecordDelivery($match, $lockedInnings)) {
                throw ValidationException::withMessages([
                    'delivery' => 'This delivery cannot be recorded right now.',
                ]);
            }

            $isWide = (bool) ($data['is_wide'] ?? false);
            $isNoBall = (bool) ($data['is_no_ball'] ?? false);
            $isFreeHit = $this->isFreeHit($lockedInnings);

            $this->assertParticipantsValid($match, $lockedInnings, $data, $isWide, $isNoBall, $isFreeHit);
            $this->assertExpectedBattingEnds($lockedInnings, $data);
            $this->assertNoConsecutiveOverBowler($lockedInnings, (int) $data['bowler_match_player_id']);

            $previousDelivery = Delivery::query()
                ->where('innings_id', $lockedInnings->id)
                ->orderByDesc('delivery_sequence')
                ->first();

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
            ]);

            $this->logMidOverBowlerChangeIfNeeded($match, $lockedInnings, $previousDelivery, $delivery, $data);

            // A recorded delivery is now the freshest source of truth for
            // batting-end state — any pending override from Change
            // Strike or a retirement is superseded and must not linger.
            if ($lockedInnings->pending_state !== null) {
                $lockedInnings->update(['pending_state' => null]);
            }

            $this->recalculateInningsTotals($lockedInnings);

            if ($this->hasReachedAutomaticCompletion($match, $lockedInnings)) {
                $lockedInnings->update(['status' => 'completed', 'completion_type' => 'automatic', 'completion_reason' => null]);
            }

            return $delivery;
        });
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
     * A mid-over bowler change (frozen rule 23) is legal — already-
     * bowled deliveries stay credited to whoever actually bowled them,
     * since bowler_match_player_id is stored per-delivery and never
     * rewritten. This only adds the audit trail: logged whenever the
     * new delivery's bowler differs from the previous delivery's bowler
     * within the SAME over (an over-boundary change is an ordinary new
     * over, not a "change", and is separately validated/allowed by
     * assertNoConsecutiveOverBowler()).
     */
    private function logMidOverBowlerChangeIfNeeded(GameMatch $match, Innings $innings, ?Delivery $previousDelivery, Delivery $newDelivery, array $data): void
    {
        if (! $previousDelivery || ! Auth::id()) {
            // No authenticated actor (e.g. a direct service call from a
            // console command or test harness, outside the normal HTTP
            // scoring flow) — an audit entry with no "who" would be
            // meaningless, and logging it must never be what breaks an
            // otherwise-valid delivery from recording.
            return;
        }

        if ((int) $previousDelivery->over_number !== (int) $newDelivery->over_number) {
            return;
        }

        if ((int) $previousDelivery->bowler_match_player_id === (int) $newDelivery->bowler_match_player_id) {
            return;
        }

        ScoringEvent::create([
            'match_id' => $match->id,
            'innings_id' => $innings->id,
            'type' => ScoringEvent::TYPE_BOWLER_CHANGE_MID_OVER,
            'reason' => $data['bowler_change_reason'] ?? 'No reason provided',
            'performed_by' => Auth::id(),
            'payload' => [
                'old_bowler_match_player_id' => (int) $previousDelivery->bowler_match_player_id,
                'new_bowler_match_player_id' => (int) $newDelivery->bowler_match_player_id,
                'over_number' => (int) $newDelivery->over_number,
            ],
        ]);
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
                'bowler_match_player_id' => 'The same bowler cannot bowl two overs in a row.',
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
    private function hasReachedAutomaticCompletion(GameMatch $match, Innings $innings): bool
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

            return true;
        });
    }

    private function isInningsUndoable(GameMatch $match, Innings $innings): bool
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
            ->sum('runs');

        $retiredOutCount = ScoringEvent::query()
            ->where('innings_id', $innings->id)
            ->where('type', ScoringEvent::TYPE_RETIRED_OUT)
            ->count();

        $innings->update([
            'total_runs' => (int) $totals->total_runs + $penaltyRunsForBattingTeam,
            'extras' => (int) $totals->extras + $penaltyRunsForBattingTeam,
            'legal_balls' => (int) $totals->legal_balls,
            'total_wickets' => (int) $totals->total_wickets + $retiredOutCount,
        ]);
    }

    /**
     * The expected striker/non-striker for the NEXT delivery of this
     * innings. Checks Innings.pending_state FIRST — an explicit override
     * written by ScoringEventService::changeStrike()/retireBatter() when
     * one is active — and otherwise derives it entirely from the latest
     * Delivery row, exactly as before. Also used by ScoringController to
     * preselect the scoring form's batter fields.
     *
     * @return array{first_ball: bool, requires_replacement: bool, striker_id: int|null, non_striker_id: int|null, survivor_id: int|null, survivor_end: string|null}
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
            ];
        }

        // Physically-run runs off the bat, on a wide, or as byes/leg-byes
        // all now contribute to strike parity (frozen rule 10) — only
        // the fixed wide/no-ball penalty runs are excluded, since they
        // correspond to no physical running. runs_physically_run, when
        // the scorer explicitly recorded it (a short run, or an unusual
        // run-out), overrides the credited total for this purpose.
        $runningRuns = $latest->runs_physically_run !== null
            ? (int) $latest->runs_physically_run
            : $latest->runs_off_bat + $latest->wide_running_runs + $latest->bye_runs + $latest->leg_bye_runs;

        $swapForRuns = $runningRuns % 2 === 1;
        $swapForOver = $latest->is_legal_delivery && $innings->legal_balls > 0 && $innings->legal_balls % 6 === 0;
        $swap = $swapForRuns !== $swapForOver;

        $preStriker = (int) $latest->striker_match_player_id;
        $preNonStriker = (int) $latest->non_striker_match_player_id;

        $nextStrikerSlot = $swap ? $preNonStriker : $preStriker;
        $nextNonStrikerSlot = $swap ? $preStriker : $preNonStriker;

        if (! $latest->is_wicket) {
            return [
                'first_ball' => false,
                'requires_replacement' => false,
                'striker_id' => $nextStrikerSlot,
                'non_striker_id' => $nextNonStrikerSlot,
                'survivor_id' => null,
                'survivor_end' => null,
            ];
        }

        $dismissedId = (int) $latest->dismissed_match_player_id;

        if ($dismissedId === $nextStrikerSlot) {
            $survivorId = $nextNonStrikerSlot;
            $survivorEnd = 'non_striker';
        } else {
            $survivorId = $nextStrikerSlot;
            $survivorEnd = 'striker';
        }

        // The scorer's explicit confirmation of the survivor's actual
        // end (frozen rule 19) overrides the computed slot when given.
        if ($latest->confirmed_survivor_end !== null) {
            $survivorEnd = $latest->confirmed_survivor_end;
        }

        return [
            'first_ball' => false,
            'requires_replacement' => true,
            'striker_id' => null,
            'non_striker_id' => null,
            'survivor_id' => $survivorId,
            'survivor_end' => $survivorEnd,
        ];
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
                'striker_match_player_id' => 'A player already dismissed or retired out in this innings cannot return to the crease.',
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
                    'striker_match_player_id' => 'Selected striker/non-striker do not match the expected batting ends.',
                ]);
            }

            $newBatterId = $state['survivor_end'] === 'striker' ? $submittedNonStriker : $submittedStriker;

            if ($newBatterId === $state['survivor_id']) {
                throw ValidationException::withMessages([
                    'striker_match_player_id' => 'The replacement batter must be a new player, not the surviving batter.',
                ]);
            }

            return;
        }

        if ($submittedStriker !== $state['striker_id'] || $submittedNonStriker !== $state['non_striker_id']) {
            throw ValidationException::withMessages([
                'striker_match_player_id' => 'Selected striker/non-striker do not match the expected batting ends.',
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
            throw ValidationException::withMessages(['striker_match_player_id' => 'The striker must be a selected player from the batting team.']);
        }

        if (! $this->matchPlayerBelongsToTeam($data['non_striker_match_player_id'], $match, $innings->batting_team_id)) {
            throw ValidationException::withMessages(['non_striker_match_player_id' => 'The non-striker must be a selected player from the batting team.']);
        }

        if ((int) $data['striker_match_player_id'] === (int) $data['non_striker_match_player_id']) {
            throw ValidationException::withMessages(['non_striker_match_player_id' => 'The striker and non-striker must be different players.']);
        }

        if (! $this->matchPlayerBelongsToTeam($data['bowler_match_player_id'], $match, $innings->bowling_team_id)) {
            throw ValidationException::withMessages(['bowler_match_player_id' => 'The bowler must be a selected player from the bowling team.']);
        }

        if (empty($data['is_wicket'])) {
            return;
        }

        $dismissedId = (int) ($data['dismissed_match_player_id'] ?? 0);

        if (! in_array($dismissedId, [(int) $data['striker_match_player_id'], (int) $data['non_striker_match_player_id']], true)) {
            throw ValidationException::withMessages(['dismissed_match_player_id' => 'The dismissed player must be the striker or non-striker on this delivery.']);
        }

        $wicketType = $data['wicket_type'] ?? null;

        if (! $wicketType || ! in_array($wicketType, $this->validWicketTypesForDelivery($isWide, $isNoBall, $isFreeHit), true)) {
            throw ValidationException::withMessages(['wicket_type' => 'This dismissal type is not valid for this kind of delivery.']);
        }

        $fielderId = $data['fielder_match_player_id'] ?? null;

        if ($this->dismissalRequiresFielder($wicketType) && ! $fielderId) {
            throw ValidationException::withMessages(['fielder_match_player_id' => 'A fielder is required for this dismissal type.']);
        }

        if ($this->dismissalForbidsFielder($wicketType) && $fielderId) {
            throw ValidationException::withMessages(['fielder_match_player_id' => 'A fielder must not be recorded for this dismissal type.']);
        }

        if ($fielderId && ! $this->matchPlayerBelongsToTeam($fielderId, $match, $innings->bowling_team_id)) {
            throw ValidationException::withMessages(['fielder_match_player_id' => 'The fielder must be a selected player from the bowling team.']);
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
