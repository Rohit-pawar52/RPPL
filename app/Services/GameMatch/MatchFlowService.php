<?php

namespace App\Services\GameMatch;

use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

/**
 * The match-day setup workflow: scheduled -> toss -> live. Deliberately
 * separate from GameMatchService, which owns fixture CRUD/scheduling —
 * this keeps that service from being overloaded with match-day
 * decisions, without introducing a duplicate abstraction (GameMatch
 * itself, MatchPlayer eligibility, and Playing-XI locking all still
 * live where Phase 3.9/3.10 put them).
 *
 * Phase 3.12 will introduce Innings. Until then, a 'live' match may
 * legitimately have zero innings for a while — that gap is intentional,
 * not a bug, and this service never creates Innings rows.
 */
class MatchFlowService
{
    /**
     * Toss may be started only from 'scheduled', only before any
     * Innings exists, and only once both participating teams have at
     * least one selected MatchPlayer. There is deliberately no "exactly
     * 11 players" rule here — no such tournament-size requirement is
     * established anywhere else in this project yet, so none is
     * invented for this phase.
     */
    public function canStartToss(GameMatch $match): bool
    {
        return $match->match_status === 'scheduled'
            && ! $this->hasInnings($match)
            && $this->teamHasSelectedPlayers($match, $match->edition_team_a_id)
            && $this->teamHasSelectedPlayers($match, $match->edition_team_b_id);
    }

    /**
     * Transitions scheduled -> toss only. Deliberately does not touch
     * toss_winner_team_id/toss_decision — those are only ever written by
     * recordToss(), kept as a separate action so the scorer/admin can
     * correct a mistaken toss entry before the match actually starts.
     */
    public function startToss(GameMatch $match): bool
    {
        if (! $this->canStartToss($match)) {
            return false;
        }

        return (bool) $match->update(['match_status' => 'toss']);
    }

    /**
     * Toss winner/decision may be recorded or corrected only while the
     * match is in the 'toss' phase and no Innings exists yet. Once
     * 'live', toss data becomes immutable through this workflow — no
     * audit/history system is built for corrections, by design.
     */
    public function canRecordToss(GameMatch $match): bool
    {
        return $match->match_status === 'toss' && ! $this->hasInnings($match);
    }

    /**
     * $data is expected to already be validated by RecordTossRequest
     * (toss_winner_team_id is exactly one of the match's two
     * participating edition_teams; toss_decision is bat/bowl). The
     * match-state gate is rechecked here regardless, since the
     * FormRequest cannot know whether the match has moved on since the
     * page was rendered.
     */
    public function recordToss(GameMatch $match, array $data): bool
    {
        if (! $this->canRecordToss($match)) {
            return false;
        }

        return (bool) $match->update([
            'toss_winner_team_id' => $data['toss_winner_team_id'],
            'toss_decision' => $data['toss_decision'],
        ]);
    }

    /**
     * The match may start only from 'toss', with both toss fields set,
     * both teams still having at least one selected player, and no
     * Innings yet.
     */
    public function canStartMatch(GameMatch $match): bool
    {
        return $match->match_status === 'toss'
            && $match->toss_winner_team_id !== null
            && $match->toss_decision !== null
            && ! $this->hasInnings($match)
            && $this->teamHasSelectedPlayers($match, $match->edition_team_a_id)
            && $this->teamHasSelectedPlayers($match, $match->edition_team_b_id);
    }

    /**
     * Locks the match row and rechecks every start condition against
     * fresh data before writing. This is the one place in this phase a
     * transaction/lock is genuinely needed: two concurrent "Start Match"
     * requests must not both independently transition the same match
     * toss -> live, so a bare canStartMatch() check followed by a
     * separate update() would not be safe here.
     */
    public function startMatch(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canStartMatch($locked)) {
                return false;
            }

            return (bool) $locked->update([
                'match_status' => 'live',
                'started_at' => now(),
            ]);
        });
    }

    /**
     * A scheduled match with no scoring activity yet may simply be
     * cancelled — no Innings can exist at this point under the normal
     * workflow, but this is re-verified anyway rather than assumed.
     */
    public function canCancelMatch(GameMatch $match): bool
    {
        return $match->match_status === 'scheduled' && ! $this->hasInnings($match);
    }

    /**
     * Locks the match row and rechecks eligibility (status still
     * 'scheduled', still no Innings) before writing, the same pattern as
     * startMatch() — a concurrent "Start Toss" must never race a
     * "Cancel Match" into an inconsistent state. No winner/margin is
     * ever fabricated; result_type is left null since cancellation
     * before match-day activity has no "outcome" to classify (unlike
     * abandonment's established 'abandoned' result_type). completed_at
     * is left null: that column is written only by
     * MatchResultService::finalizeMatch() and has never meant anything
     * beyond "successfully completed".
     */
    public function cancelMatch(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canCancelMatch($locked)) {
                return false;
            }

            return (bool) $locked->update([
                'match_status' => 'cancelled',
                'match_result' => 'Match cancelled',
                'winner_team_id' => null,
                'result_type' => null,
                'win_margin_type' => null,
                'win_margin' => null,
            ]);
        });
    }

    /**
     * A match that has entered match-day activity (toss recorded/in
     * progress, or live) but has not yet completed may be abandoned.
     * Deliberately excludes 'scheduled' (use cancelMatch() instead),
     * 'completed' (the result is already locked in), and 'abandoned'/
     * 'cancelled' (already terminal) — this is a distinct action from
     * cancellation, not a superset of it.
     */
    public function canAbandonMatch(GameMatch $match): bool
    {
        return in_array($match->match_status, ['toss', 'live'], true);
    }

    /**
     * Locks the match row and rechecks eligibility before writing, same
     * pattern as startMatch()/cancelMatch(). Never touches Innings or
     * Delivery rows — any existing scoring history is left completely
     * untouched, only the parent match's own lifecycle/result fields
     * change. result_type is set to the existing 'abandoned' schema
     * value (never auto-converted to 'no_result' — that tournament-
     * policy decision is explicitly out of scope here). completed_at is
     * left null for the same reason as cancelMatch() above.
     */
    public function abandonMatch(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canAbandonMatch($locked)) {
                return false;
            }

            return (bool) $locked->update([
                'match_status' => 'abandoned',
                'result_type' => 'abandoned',
                'match_result' => 'Match abandoned',
                'winner_team_id' => null,
                'win_margin_type' => null,
                'win_margin' => null,
            ]);
        });
    }

    private function hasInnings(GameMatch $match): bool
    {
        return $match->innings()->exists();
    }

    private function teamHasSelectedPlayers(GameMatch $match, int $editionTeamId): bool
    {
        return $match->matchPlayers()
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $editionTeamId))
            ->exists();
    }
}
