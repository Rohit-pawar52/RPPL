<?php

namespace App\Services\Innings;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\ScoringEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Innings lifecycle: which team bats/bowls first, creating Innings #1
 * and #2, the explicit manual "complete innings" action (distinct from
 * DeliveryService's automatic completion), and reopening a completed
 * innings for correction. Deliberately separate from MatchFlowService,
 * which owns the scheduled -> toss -> live workflow and stops there —
 * this service starts where that one ends and never touches
 * match_status itself.
 */
class InningsService
{
    /**
     * Innings #1's batting/bowling teams are derived from the toss, and
     * ONLY from the toss — never chosen manually. If the toss winner
     * chose to bat, they bat first; if they chose to bowl, the other
     * team bats first.
     *
     * @return array{batting_team_id: int, bowling_team_id: int}
     */
    public function determineFirstInningsTeams(GameMatch $match): array
    {
        $otherTeamId = (int) $match->toss_winner_team_id === (int) $match->edition_team_a_id
            ? $match->edition_team_b_id
            : $match->edition_team_a_id;

        return $match->toss_decision === 'bat'
            ? ['batting_team_id' => $match->toss_winner_team_id, 'bowling_team_id' => $otherTeamId]
            : ['batting_team_id' => $otherTeamId, 'bowling_team_id' => $match->toss_winner_team_id];
    }

    /**
     * Innings #1 may be started only once the match is live, the toss
     * has been recorded, no innings exists for this match at all yet,
     * and both teams still have exactly 11 selected MatchPlayers (frozen
     * S02 rule 1 — MatchFlowService::canStartMatch() already enforces
     * this before the match can even go live, so this is defense-in-
     * depth against Playing XI rows somehow changing in between).
     */
    public function canStartFirstInnings(GameMatch $match): bool
    {
        return $match->match_status === 'live'
            && $match->toss_winner_team_id !== null
            && $match->toss_decision !== null
            && ! $match->innings()->exists()
            && $this->teamHasExactlyElevenPlayers($match, $match->edition_team_a_id)
            && $this->teamHasExactlyElevenPlayers($match, $match->edition_team_b_id);
    }

    /**
     * Locks the match row and rechecks every start condition against
     * fresh data before creating Innings #1 — the same "two concurrent
     * requests must not both succeed" reasoning as
     * MatchFlowService::startMatch(). The DB unique constraint on
     * (match_id, innings_number) remains the final guarantee.
     */
    public function startFirstInnings(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canStartFirstInnings($locked)) {
                return false;
            }

            $teams = $this->determineFirstInningsTeams($locked);

            Innings::create([
                'match_id' => $locked->id,
                'innings_number' => 1,
                'batting_team_id' => $teams['batting_team_id'],
                'bowling_team_id' => $teams['bowling_team_id'],
                'status' => 'live',
                // legal_balls/total_runs/total_wickets/extras are left
                // out deliberately — the migration already defaults all
                // four to 0, so there is nothing meaningful to assign
                // here beyond what the database already guarantees.
            ]);

            return true;
        });
    }

    /**
     * An innings may be manually completed only while it belongs to
     * this match, the match is still live, and the innings itself is
     * still live. This is a deliberate scorer/admin judgment call (an
     * innings declaration, a safety call, or any other reason not
     * captured by the objective automatic-completion conditions in
     * DeliveryService::hasReachedAutomaticCompletion()) — it does not
     * itself verify 10 wickets/overs exhausted/target reached, and never
     * needs to: automatic completion already handles those cases
     * without this action. It DOES require at least one legal delivery
     * to have been recorded, so an innings can never be "completed" at
     * an impossible 0/0, zero-ball state.
     */
    public function canCompleteInnings(GameMatch $match, Innings $innings): bool
    {
        return $innings->match_id === $match->id
            && in_array($innings->innings_number, [1, 2], true)
            && $match->match_status === 'live'
            && $innings->status === 'live'
            && $innings->legal_balls > 0;
    }

    /**
     * Locks the innings row and rechecks before transitioning it.
     * Records completion_type='manual' with the mandatory reason (frozen
     * S02 rule 15) — the same stored fact DeliveryService::
     * undoLastDelivery() now trusts to decide whether undo may reopen
     * this innings (never, for a manual completion; see
     * reopenInnings() for the authorized correction path instead).
     * Deliberately never touches match_status/result fields — those
     * belong to MatchResultService::finalizeMatch().
     */
    public function completeInnings(GameMatch $match, Innings $innings, string $reason): bool
    {
        return DB::transaction(function () use ($match, $innings, $reason) {
            $locked = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->canCompleteInnings($match, $locked)) {
                return false;
            }

            return (bool) $locked->update([
                'status' => 'completed',
                'completion_type' => 'manual',
                'completion_reason' => $reason,
            ]);
        });
    }

    /**
     * Reopen a completed innings (frozen S02 rule 16): available to
     * admin/scorer (the same manageInnings ability as the rest of this
     * service) for either an automatically or a manually completed
     * innings, as long as the parent match itself is still 'live' —
     * reopening an innings that belongs to an already-FINALIZED match is
     * a distinct, admin-only action (MatchResultService::reopenMatch())
     * that must happen first. Existing deliveries are never touched;
     * only the innings' own status/completion metadata changes, so
     * scoring can resume exactly where it left off.
     */
    public function canReopenInnings(GameMatch $match, Innings $innings): bool
    {
        return $innings->match_id === $match->id
            && in_array($innings->innings_number, [1, 2], true)
            && $match->match_status === 'live'
            && $innings->status === 'completed';
    }

    public function reopenInnings(GameMatch $match, Innings $innings, string $reason, User $performedBy): bool
    {
        return DB::transaction(function () use ($match, $innings, $reason, $performedBy) {
            $locked = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->canReopenInnings($match, $locked)) {
                return false;
            }

            ScoringEvent::create([
                'match_id' => $match->id,
                'innings_id' => $locked->id,
                'type' => ScoringEvent::TYPE_INNINGS_REOPENED,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
                'payload' => ['previous_completion_type' => $locked->completion_type],
            ]);

            return (bool) $locked->update([
                'status' => 'live',
                'completion_type' => null,
                'completion_reason' => null,
            ]);
        });
    }

    /**
     * Innings #2 may be started only once Innings #1 exists and is
     * completed, Innings #2 does not already exist, the match is still
     * live, and both teams still have exactly 11 selected MatchPlayers.
     * There is no third-innings path anywhere in this service — normal
     * limited-overs matches have exactly two innings; a tied match's
     * Super Over result is recorded separately by
     * MatchResultService::recordSuperOverResult() without ball-by-ball
     * scoring (frozen S02 rule 7).
     */
    public function canStartSecondInnings(GameMatch $match): bool
    {
        $firstInnings = $match->firstInnings;

        return $match->match_status === 'live'
            && $firstInnings !== null
            && $firstInnings->status === 'completed'
            && $match->secondInnings === null
            && $this->teamHasExactlyElevenPlayers($match, $match->edition_team_a_id)
            && $this->teamHasExactlyElevenPlayers($match, $match->edition_team_b_id);
    }

    /**
     * Innings #2's teams are always the exact reverse of Innings #1 —
     * read from Innings #1 itself, never recalculated independently
     * from the toss, so the two innings can never disagree about which
     * team is which. Locks both the match row and the Innings #1 row
     * before rechecking, for the same concurrent-request reasoning as
     * startFirstInnings().
     */
    public function startSecondInnings(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canStartSecondInnings($locked)) {
                return false;
            }

            $firstInnings = Innings::query()
                ->where('match_id', $locked->id)
                ->where('innings_number', 1)
                ->lockForUpdate()
                ->firstOrFail();

            Innings::create([
                'match_id' => $locked->id,
                'innings_number' => 2,
                'batting_team_id' => $firstInnings->bowling_team_id,
                'bowling_team_id' => $firstInnings->batting_team_id,
                'status' => 'live',
            ]);

            return true;
        });
    }

    private function teamHasExactlyElevenPlayers(GameMatch $match, int $editionTeamId): bool
    {
        return $match->matchPlayers()
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeamId))
            ->count() === 11;
    }
}
