<?php

namespace App\Services\Innings;

use App\Models\GameMatch;
use App\Models\Innings;
use Illuminate\Support\Facades\DB;

/**
 * Innings lifecycle and initialization: which team bats/bowls first,
 * creating Innings #1 and #2, and the explicit (temporary,
 * scoring-engine-free) "complete innings" action. Deliberately separate
 * from MatchFlowService, which owns the scheduled -> toss -> live
 * workflow and stops there — this service starts where that one ends
 * and never touches match_status itself.
 *
 * No ball-by-ball scoring exists yet: completeInnings() is a plain
 * administrative/scorer transition (live -> completed), not derived
 * from 10 wickets/overs exhausted/target reached. A future scoring
 * engine will replace or constrain this once Delivery data exists.
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
     * and both teams still have at least one selected MatchPlayer.
     * There is deliberately no "exactly 11 players" rule — no such
     * tournament-size requirement is established anywhere else in this
     * project.
     */
    public function canStartFirstInnings(GameMatch $match): bool
    {
        return $match->match_status === 'live'
            && $match->toss_winner_team_id !== null
            && $match->toss_decision !== null
            && ! $match->innings()->exists()
            && $this->teamHasSelectedPlayers($match, $match->edition_team_a_id)
            && $this->teamHasSelectedPlayers($match, $match->edition_team_b_id);
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
     * An innings may be completed only while it belongs to this match,
     * the match is still live, and the innings itself is still live.
     * This is a deliberately conservative, purely administrative
     * transition — it does not (and cannot yet) verify 10 wickets, overs
     * exhausted, or a chased target, since no automatic completion rule
     * exists for a manual close. It DOES require at least one legal
     * delivery to have been recorded: without that floor, an admin could
     * complete an innings the instant it starts, producing an impossible
     * 0/0, zero-ball "completed" innings (and, if done to both innings,
     * a nonsensical tied/completed match with no scoring at all). A
     * future scoring engine may replace or further constrain this.
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
     * Locks the innings row and rechecks before transitioning it —
     * future scoring writes (once Delivery exists) must not race a
     * concurrent completion of the same innings.
     *
     * Deliberately never touches match_status, result_type,
     * match_result, winner_team_id, or completed_at — result
     * calculation belongs to a later finalization phase. It is
     * therefore valid, and expected, for a match to remain 'live' with
     * both innings 'completed' until that phase exists.
     */
    public function completeInnings(GameMatch $match, Innings $innings): bool
    {
        return DB::transaction(function () use ($match, $innings) {
            $locked = Innings::query()->whereKey($innings->id)->lockForUpdate()->firstOrFail();

            if (! $this->canCompleteInnings($match, $locked)) {
                return false;
            }

            return (bool) $locked->update(['status' => 'completed']);
        });
    }

    /**
     * Innings #2 may be started only once Innings #1 exists and is
     * completed, Innings #2 does not already exist, the match is still
     * live, and both teams still have at least one selected
     * MatchPlayer. There is no third-innings path anywhere in this
     * service — normal limited-overs matches have exactly two innings;
     * super overs are out of scope.
     */
    public function canStartSecondInnings(GameMatch $match): bool
    {
        $firstInnings = $match->firstInnings;

        return $match->match_status === 'live'
            && $firstInnings !== null
            && $firstInnings->status === 'completed'
            && $match->secondInnings === null
            && $this->teamHasSelectedPlayers($match, $match->edition_team_a_id)
            && $this->teamHasSelectedPlayers($match, $match->edition_team_b_id);
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

    private function teamHasSelectedPlayers(GameMatch $match, int $editionTeamId): bool
    {
        return $match->matchPlayers()
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeamId))
            ->exists();
    }
}
