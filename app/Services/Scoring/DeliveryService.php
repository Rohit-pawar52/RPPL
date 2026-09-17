<?php

namespace App\Services\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The ball-by-ball scoring engine. Delivery rows are the authoritative
 * historical record; Innings.total_runs/extras/legal_balls/total_wickets
 * are caches this service rebuilds by aggregating Delivery rows — they
 * are never written independently (see recalculateInningsTotals()).
 *
 * Schema note: `deliveries` has four separate extra-run columns
 * (wide_runs/no_ball_runs/bye_runs/leg_bye_runs) rather than a single
 * extra_type+extra_runs pair, and no dedicated "extra_type" column.
 * Delivery::EXTRA_TYPES/the extra_type request field are a request/UI
 * convenience only — this service maps the scorer's single choice onto
 * the real columns; nothing invents a schema column that doesn't exist.
 *
 * Ordering note: delivery_sequence (unique per innings, strictly
 * increasing, includes illegal deliveries) is the authoritative
 * chronological/ordering key. over_number/ball_number are a secondary,
 * purely presentational cricket-notation label derived from
 * legal_balls-so-far — they are NOT unique (by schema design: the
 * unique constraint is only on (innings_id, delivery_sequence)), so
 * consecutive illegal deliveries (e.g. two wides in a row) legitimately
 * share the same over_number/ball_number, exactly as a real scoreboard
 * would show "0.3, wide" then "0.3" again for the retry. This is not an
 * ambiguity bug — delivery_sequence still totally orders every ball.
 *
 * Strike rotation (Phase 3.33): the expected striker/non-striker for
 * the NEXT delivery is derived entirely from the latest Delivery row
 * (see expectedBattingState()) — never persisted on Innings, so undo
 * naturally restores the correct expected state simply by removing the
 * latest row. Bowler selection remains entirely manual; this project
 * has no bowler-quota/consecutive-over rule. Documented limitation: a
 * wide's total run value (wide_runs) is a single lumped column with no
 * way to tell a mandatory penalty run apart from additional runs
 * physically run between the wickets, so — per this phase's explicit
 * instruction not to invent behavior the schema cannot represent —
 * wide_runs never contributes to strike-rotation parity; only
 * runs_off_bat + bye_runs + leg_bye_runs do.
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

    /**
     * Once legal_balls reaches overs_per_innings * 6, no further
     * delivery may be recorded. In practice this guard is now redundant
     * with hasReachedAutomaticCompletion() below (Phase 3.32): reaching
     * the limit already transitions innings.status to 'completed' in
     * the same recordDelivery() call that reached it, which independently
     * makes canRecordDelivery() return false via isInningsScorable().
     * Left in place as a harmless, cheap secondary check rather than
     * removed, since no actual hole exists.
     */
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
     * everything else (a normal ball, or one scored as a bye/leg-bye)
     * does. Centralized here so it is never left to client input.
     */
    public function determineLegality(?string $extraType): bool
    {
        return ! in_array($extraType, ['wide', 'no_ball'], true);
    }

    /**
     * Maps the scorer's single extra-type choice (or none) onto the
     * actual runs_off_bat/wide_runs/no_ball_runs/bye_runs/leg_bye_runs
     * columns. A bat cannot touch the ball on a wide, and byes/leg-byes
     * by definition did not come off the bat, so runs_off_bat is forced
     * to 0 for those three; a no-ball's mandatory single penalty run is
     * fixed at 1, with any runs the batter actually hit off it recorded
     * separately in runs_off_bat (never folded into no_ball_runs).
     *
     * @return array{runs_off_bat: int, wide_runs: int, no_ball_runs: int, bye_runs: int, leg_bye_runs: int}
     */
    public function calculateDeliveryRuns(?string $extraType, int $extraAmount, int $runsOffBat): array
    {
        return match ($extraType) {
            'wide' => ['runs_off_bat' => 0, 'wide_runs' => $extraAmount, 'no_ball_runs' => 0, 'bye_runs' => 0, 'leg_bye_runs' => 0],
            'no_ball' => ['runs_off_bat' => $runsOffBat, 'wide_runs' => 0, 'no_ball_runs' => 1, 'bye_runs' => 0, 'leg_bye_runs' => 0],
            'bye' => ['runs_off_bat' => 0, 'wide_runs' => 0, 'no_ball_runs' => 0, 'bye_runs' => $extraAmount, 'leg_bye_runs' => 0],
            'leg_bye' => ['runs_off_bat' => 0, 'wide_runs' => 0, 'no_ball_runs' => 0, 'bye_runs' => 0, 'leg_bye_runs' => $extraAmount],
            default => ['runs_off_bat' => $runsOffBat, 'wide_runs' => 0, 'no_ball_runs' => 0, 'bye_runs' => 0, 'leg_bye_runs' => 0],
        };
    }

    /**
     * Conservative, explicit combinations only — real cricket law has
     * more nuance than this, but these are the two well-established
     * rules the prompt itself calls out (a batter cannot be bowled/
     * caught/lbw/hit-wicket off a delivery that was never fair), kept
     * simple rather than modeling every edge case.
     *
     * @return list<string>
     */
    public function validWicketTypesForExtraType(?string $extraType): array
    {
        return match ($extraType) {
            'wide' => ['stumped', 'run_out'],
            'no_ball' => ['run_out', 'obstructing_field'],
            default => Delivery::WICKET_TYPES,
        };
    }

    public function dismissalRequiresFielder(string $wicketType): bool
    {
        return in_array($wicketType, ['caught', 'stumped'], true);
    }

    public function dismissalForbidsFielder(string $wicketType): bool
    {
        return in_array($wicketType, ['bowled', 'lbw', 'hit_wicket'], true);
    }
    // run_out/obstructing_field: fielder is optional — neither required nor forbidden.

    /**
     * Every wicket_type this schema supports (bowled, caught, lbw,
     * stumped, hit_wicket, run_out, obstructing_field) is a genuine
     * "batter is out" dismissal — there is no retirement-style type
     * here that would need excluding from the wicket count. Still
     * centralized rather than assumed inline, so recalculateInnings
     * Totals() has one place to change if a non-out type is ever added.
     */
    public function dismissalCountsAsWicket(string $wicketType): bool
    {
        return in_array($wicketType, Delivery::WICKET_TYPES, true);
    }

    /**
     * Records one delivery and rebuilds the innings' cached totals,
     * atomically. Locks the Innings row (the serialization point for
     * all scoring on this innings), rechecks every eligibility
     * condition against fresh data, determines delivery_sequence/
     * over_number/ball_number from the locked row's current state, then
     * writes the Delivery and rebuilds the cache — all inside one
     * transaction, so two concurrent "record delivery" requests for the
     * same innings can never both succeed with an inconsistent result.
     *
     * $data is expected to already be validated by StoreDeliveryRequest
     * (participant eligibility, wicket/extra shape). The core rules are
     * re-verified here regardless — the same defense-in-depth pattern
     * used by every other service in this project.
     */
    public function recordDelivery(GameMatch $match, Innings $innings, array $data): Delivery
    {
        return DB::transaction(function () use ($match, $innings, $data) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->canRecordDelivery($match, $lockedInnings)) {
                throw ValidationException::withMessages([
                    'delivery' => 'This delivery cannot be recorded right now.',
                ]);
            }

            $this->assertParticipantsValid($match, $lockedInnings, $data);
            $this->assertExpectedBattingEnds($lockedInnings, $data);

            $extraType = $data['extra_type'] ?? null;
            $runs = $this->calculateDeliveryRuns($extraType, (int) ($data['extra_amount'] ?? 0), (int) ($data['runs_off_bat'] ?? 0));
            $isLegal = $this->determineLegality($extraType);
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
                'no_ball_runs' => $runs['no_ball_runs'],
                'bye_runs' => $runs['bye_runs'],
                'leg_bye_runs' => $runs['leg_bye_runs'],
                'penalty_runs' => 0,
                'total_runs' => array_sum($runs),
                'is_legal_delivery' => $isLegal,
                'is_wicket' => $isWicket,
                'wicket_type' => $isWicket ? $data['wicket_type'] : null,
                'dismissed_match_player_id' => $isWicket ? $data['dismissed_match_player_id'] : null,
                'fielder_match_player_id' => $isWicket ? ($data['fielder_match_player_id'] ?? null) : null,
                'commentary' => $data['commentary'] ?? null,
            ]);

            $this->recalculateInningsTotals($lockedInnings);

            if ($this->hasReachedAutomaticCompletion($match, $lockedInnings)) {
                $lockedInnings->update(['status' => 'completed']);
            }

            return $delivery;
        });
    }

    /**
     * The three, and only three, objective conditions that end an
     * innings automatically (Phase 3.32): all out, the match's
     * configured legal-ball limit reached, or — second innings only —
     * the chase target (first innings runs + 1) reached. Never requires
     * the over to finish, never applies target logic to the first
     * innings. Reused both to decide whether to auto-complete right
     * after recording a delivery, and by undoLastDelivery() to safely
     * tell an automatically-completed innings apart from a manually-
     * completed one (see that method's docblock) — $innings is expected
     * to already carry freshly recalculated totals.
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
     * undoable innings and rebuilds the cache from what remains.
     * Deliberately the only correction mechanism in this phase — no
     * generic Delivery edit/delete — because later deliveries may
     * depend on the previous ball's participants/sequence; only ever
     * undoing the latest ball keeps the history always consistent.
     *
     * An innings that is already 'completed' may still be undone, but
     * ONLY when that completion was automatic (Phase 3.32), never when
     * an admin/scorer completed it manually via InningsService::
     * completeInnings(). The two are told apart without any persisted
     * "completion reason" by a closed-loop argument: manual completion
     * can only ever be invoked while canCompleteInnings() sees the
     * innings still 'live', and automatic completion always fires
     * immediately (same transaction) the instant an objective condition
     * becomes true — so by the time a manual completion is possible, no
     * objective condition can yet be true, and it can never become true
     * afterwards without another Delivery being recorded (which a
     * completed innings no longer accepts). Consequently: if the
     * innings' CURRENT totals still satisfy hasReachedAutomaticCompletion(),
     * its 'completed' status can only have come from automatic
     * completion; if they don't, it can only have come from a manual
     * completion, which undo must never reopen.
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
                $lockedInnings->update(['status' => 'live']);
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

        return $innings->status === 'completed' && $this->hasReachedAutomaticCompletion($match, $innings);
    }

    /**
     * The canonical cache-rebuild mechanism: aggregates directly from
     * Delivery rows rather than trusting incremental increments, so
     * recordDelivery() and undoLastDelivery() share one source of truth
     * and correction can never leave the cache out of sync. SUM(is_wicket)
     * is equivalent to counting dismissalCountsAsWicket() rows here
     * because every wicket_type this schema supports already passes
     * that check (see its docblock) — if that ever stops being true,
     * this query must switch to filtering by wicket_type explicitly.
     */
    public function recalculateInningsTotals(Innings $innings): void
    {
        $totals = Delivery::query()
            ->where('innings_id', $innings->id)
            ->selectRaw('
                COALESCE(SUM(total_runs), 0) as total_runs,
                COALESCE(SUM(wide_runs + no_ball_runs + bye_runs + leg_bye_runs + penalty_runs), 0) as extras,
                COALESCE(SUM(CASE WHEN is_legal_delivery THEN 1 ELSE 0 END), 0) as legal_balls,
                COALESCE(SUM(CASE WHEN is_wicket THEN 1 ELSE 0 END), 0) as total_wickets
            ')
            ->first();

        $innings->update([
            'total_runs' => (int) $totals->total_runs,
            'extras' => (int) $totals->extras,
            'legal_balls' => (int) $totals->legal_balls,
            'total_wickets' => (int) $totals->total_wickets,
        ]);
    }

    /**
     * The expected striker/non-striker for the NEXT delivery of this
     * innings, derived entirely from the latest Delivery row — never
     * persisted (Phase 3.33 explicitly avoids an
     * innings.current_striker_id-style column), so undo naturally
     * restores the correct expected state simply by the triggering
     * Delivery disappearing. Also used by ScoringController to
     * preselect the scoring form's batter fields.
     *
     * A dismissal doesn't change which "slot" (striker vs non-striker)
     * rotation itself would have assigned to each of the two batters
     * for the next ball — it only means one of those two slots needs a
     * new occupant. So the same rotation math (run parity XOR over-end)
     * that decides "who's on strike next" for an ordinary delivery is
     * computed first regardless of the wicket, and only then is the
     * dismissed player's slot marked vacant.
     *
     * @return array{first_ball: bool, requires_replacement: bool, striker_id: int|null, non_striker_id: int|null, survivor_id: int|null, survivor_end: string|null}
     */
    public function expectedBattingState(Innings $innings): array
    {
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

        // Wide-run totals cannot be split into "mandatory penalty" vs
        // "runs physically run" (see the class docblock) — deliberately
        // excluded here. no_ball_runs is always exactly 1 (the fixed
        // penalty; any runs the batter actually ran off a no-ball are
        // already in runs_off_bat), so it needs no special-casing.
        $runningRuns = $latest->runs_off_bat + $latest->bye_runs + $latest->leg_bye_runs;
        $swapForRuns = $runningRuns % 2 === 1;
        $swapForOver = $latest->is_legal_delivery && $innings->legal_balls > 0 && $innings->legal_balls % 6 === 0;
        // `!==` rather than `xor`: `xor` binds looser than `=`, so
        // `$swap = $a xor $b` would actually assign `$swap = $a` and
        // silently discard the `xor $b` half — `!==` is the correct,
        // precedence-safe boolean XOR for two booleans.
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
     * Every match_player ever recorded as dismissed in this innings —
     * derived from Delivery history (never a separate table/cache), so
     * a player who has already been given out cannot be selected again
     * as striker/non-striker/replacement batter.
     *
     * @return list<int>
     */
    public function dismissedMatchPlayerIds(Innings $innings): array
    {
        return Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('is_wicket', true)
            ->pluck('dismissed_match_player_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Enforces Phase 3.33's strike-rotation rules against the submitted
     * striker/non-striker: the first delivery of an innings is a free
     * choice (validated only by assertParticipantsValid()'s ordinary
     * team-membership rules), but every delivery after that must supply
     * exactly the pair expectedBattingState() derives — or, after a
     * wicket, the correct surviving batter at their correct end plus
     * any new, not-yet-dismissed batter for the vacant end.
     */
    private function assertExpectedBattingEnds(Innings $innings, array $data): void
    {
        $submittedStriker = (int) $data['striker_match_player_id'];
        $submittedNonStriker = (int) $data['non_striker_match_player_id'];

        $dismissed = $this->dismissedMatchPlayerIds($innings);

        if (in_array($submittedStriker, $dismissed, true) || in_array($submittedNonStriker, $dismissed, true)) {
            throw ValidationException::withMessages([
                'striker_match_player_id' => 'A player already dismissed in this innings cannot return to the crease.',
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
     * depth pattern used throughout this project (e.g.
     * MatchPlayerService::addPlayer()).
     */
    private function assertParticipantsValid(GameMatch $match, Innings $innings, array $data): void
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

        if (! $wicketType || ! in_array($wicketType, $this->validWicketTypesForExtraType($data['extra_type'] ?? null), true)) {
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
