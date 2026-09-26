<?php

namespace App\Services\Scoring;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\ScoringEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Non-delivery scoring events: actions that change who is at the crease,
 * or add runs, without ever creating a Delivery row. Each one is a
 * reasoned, audited ScoringEvent (see that model/migration's docblocks)
 * and, where it affects the batting-end state, an update to
 * Innings.pending_state — the single override DeliveryService::
 * expectedBattingState() checks before falling back to its normal
 * Delivery-derived computation. pending_state is always cleared the
 * next time a real Delivery is recorded, so it can never linger stale.
 *
 * Deliberately a separate service from DeliveryService (already a large
 * class) rather than folded into it — these are conceptually distinct
 * actions (corrections/awards, not balls bowled), even though they
 * share DeliveryService's cache-rebuild and batting-state machinery.
 */
class ScoringEventService
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    /**
     * Change Strike (frozen rule 20): corrects the actual striker/non-
     * striker ends without creating a delivery, changing runs/wickets,
     * or advancing the legal-ball count. Both players must currently be
     * eligible to bat (selected for the batting team, not dismissed/
     * retired out) and different from each other. Requires at least one
     * delivery already recorded — there is no "wrong strike" to correct
     * before the first ball of an innings, where either order is a free
     * choice.
     */
    public function changeStrike(GameMatch $match, Innings $innings, int $strikerId, int $nonStrikerId, string $reason, User $performedBy): void
    {
        DB::transaction(function () use ($match, $innings, $strikerId, $nonStrikerId, $reason, $performedBy) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            $state = $this->deliveries->expectedBattingState($lockedInnings);

            if ($state['first_ball']) {
                throw ValidationException::withMessages([
                    'striker_match_player_id' => 'There is no recorded strike to correct before the first delivery of this innings.',
                ]);
            }

            if ($strikerId === $nonStrikerId) {
                throw ValidationException::withMessages([
                    'non_striker_match_player_id' => 'The striker and non-striker must be different players.',
                ]);
            }

            $dismissed = $this->deliveries->dismissedMatchPlayerIds($lockedInnings);

            foreach ([$strikerId, $nonStrikerId] as $matchPlayerId) {
                if (! $this->deliveries->matchPlayerBelongsToTeam($matchPlayerId, $match, $lockedInnings->batting_team_id)) {
                    throw ValidationException::withMessages(['striker_match_player_id' => 'Both players must be selected members of the batting team.']);
                }

                if (in_array($matchPlayerId, $dismissed, true)) {
                    throw ValidationException::withMessages(['striker_match_player_id' => 'A player already dismissed or retired out cannot be at the crease.']);
                }
            }

            ScoringEvent::create([
                'match_id' => $match->id,
                'innings_id' => $lockedInnings->id,
                'type' => ScoringEvent::TYPE_CHANGE_STRIKE,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
                'payload' => ['striker_match_player_id' => $strikerId, 'non_striker_match_player_id' => $nonStrikerId],
            ]);

            $lockedInnings->update([
                'pending_state' => [
                    'first_ball' => false,
                    'requires_replacement' => false,
                    'striker_id' => $strikerId,
                    'non_striker_id' => $nonStrikerId,
                    'survivor_id' => null,
                    'survivor_end' => null,
                ],
            ]);
        });
    }

    /**
     * Retired Hurt/Out (frozen rules 4/5): the retiring batter must be
     * one of the two currently-at-the-crease players. Retired Hurt is
     * NOT a wicket and the batter remains eligible to return later as
     * the new batter for a vacant end; Retired Out counts as a wicket
     * (via ScoringEvent, never a Delivery — see
     * DeliveryService::recalculateInningsTotals()) and the batter can
     * never return. Either way, existing batting statistics for that
     * player are untouched — they are entirely Delivery-derived
     * (ScorecardService), and this action never touches Delivery rows.
     */
    public function retireBatter(GameMatch $match, Innings $innings, MatchPlayer $batter, string $type, string $reason, User $performedBy): void
    {
        if (! in_array($type, ['hurt', 'out'], true)) {
            throw ValidationException::withMessages(['type' => 'Invalid retirement type.']);
        }

        DB::transaction(function () use ($match, $innings, $batter, $type, $reason, $performedBy) {
            $lockedInnings = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            $state = $this->deliveries->expectedBattingState($lockedInnings);

            if ($state['first_ball'] || $state['requires_replacement']) {
                throw ValidationException::withMessages([
                    'match_player_id' => 'There is no batter currently at the crease to retire.',
                ]);
            }

            $batterId = $batter->id;

            if ($batterId !== $state['striker_id'] && $batterId !== $state['non_striker_id']) {
                throw ValidationException::withMessages([
                    'match_player_id' => 'The selected player is not currently batting in this innings.',
                ]);
            }

            $survivorEnd = $batterId === $state['striker_id'] ? 'non_striker' : 'striker';
            $survivorId = $batterId === $state['striker_id'] ? $state['non_striker_id'] : $state['striker_id'];

            $eventType = $type === 'out' ? ScoringEvent::TYPE_RETIRED_OUT : ScoringEvent::TYPE_RETIRED_HURT;

            ScoringEvent::create([
                'match_id' => $match->id,
                'innings_id' => $lockedInnings->id,
                'type' => $eventType,
                'match_player_id' => $batterId,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
            ]);

            $lockedInnings->update([
                'pending_state' => [
                    'first_ball' => false,
                    'requires_replacement' => true,
                    'striker_id' => null,
                    'non_striker_id' => null,
                    'survivor_id' => $survivorId,
                    'survivor_end' => $survivorEnd,
                ],
            ]);

            if ($type === 'out') {
                $this->deliveries->recalculateInningsTotals($lockedInnings);
            }
        });
    }

    /**
     * The standard international-law penalty award (frozen S02
     * correction rule 5) — RPPL has no established need for any other
     * amount, so this action deliberately does not accept an arbitrary
     * custom value.
     */
    public const STANDARD_PENALTY_RUNS = 5;

    /**
     * Penalty runs (frozen rule 6, corrected accounting per the S02
     * penalty-run-accounting follow-up): a separate ScoringEvent, never
     * a Delivery — never affects ball count, strike, or batter/bowler
     * figures. $innings is only the CONTEXT the award was recorded from
     * (which screen the admin/scorer was viewing) and is stored purely
     * for audit on the event itself; it is NOT necessarily the innings
     * that receives the credit.
     *
     * Credit always follows the awarded TEAM, not the viewing context:
     * DeliveryService::recalculateInningsTotals() sums every
     * ScoringEvent for this match where awarded_team_id matches an
     * innings' own batting_team_id, looked up fresh every time. That
     * single rule already covers every case a two-innings match can
     * produce:
     *   - awarded to the side currently batting -> its own (live)
     *     innings picks it up immediately, below.
     *   - awarded to the fielding side, which has ALREADY completed its
     *     own innings earlier in this match -> that completed innings'
     *     total is corrected immediately, below.
     *   - awarded to the fielding side, which has NOT YET batted -> no
     *     Innings row exists for them yet, so there is nothing to
     *     recalculate now; InningsService::startFirstInnings()/
     *     startSecondInnings() calls recalculateInningsTotals() on the
     *     newly created innings, which then picks up this exact same
     *     ScoringEvent automatically — the award is never copied,
     *     moved, or duplicated, only read again from the one
     *     authoritative record.
     *
     * A finalized match's stored result must never go stale silently:
     * once match_status is 'completed', a penalty is refused here until
     * the match is explicitly reopened via MatchResultService::
     * reopenMatch() (frozen S02 rule 17) — re-finalizing afterward then
     * naturally recomputes the result from the corrected totals.
     */
    public function awardPenaltyRuns(GameMatch $match, Innings $innings, EditionTeam $awardedTeam, string $reason, User $performedBy): void
    {
        $participatingTeamIds = [(int) $match->edition_team_a_id, (int) $match->edition_team_b_id];

        if (! in_array((int) $awardedTeam->id, $participatingTeamIds, true)) {
            throw ValidationException::withMessages(['awarded_team_id' => 'The awarded team must be one of the two teams in this match.']);
        }

        if ($match->match_status === 'completed') {
            throw ValidationException::withMessages([
                'awarded_team_id' => 'This match has already been finalized. Reopen it before awarding penalty runs, so the corrected result can be recalculated.',
            ]);
        }

        DB::transaction(function () use ($match, $innings, $awardedTeam, $reason, $performedBy) {
            ScoringEvent::create([
                'match_id' => $match->id,
                'innings_id' => $innings->id,
                'type' => ScoringEvent::TYPE_PENALTY_RUNS,
                'awarded_team_id' => $awardedTeam->id,
                'runs' => self::STANDARD_PENALTY_RUNS,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
            ]);

            $targetInnings = Innings::query()
                ->where('match_id', $match->id)
                ->where('batting_team_id', $awardedTeam->id)
                ->lockForUpdate()
                ->first();

            // No Innings row for the awarded team yet (it hasn't batted
            // in this match) — nothing to recalculate now; see this
            // method's docblock for how the credit is still guaranteed
            // to apply once that innings is created.
            if ($targetInnings) {
                $this->deliveries->recalculateInningsTotals($targetInnings);
            }
        });
    }
}
