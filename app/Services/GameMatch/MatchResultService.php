<?php

namespace App\Services\GameMatch;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\ScoringEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Derives and stores a normal two-innings match result from completed
 * Innings totals, and performs the explicit "Finalize Match" live ->
 * completed transition. Deliberately separate from InningsService
 * (which owns individual innings lifecycle and never touches
 * match_status) and from MatchFlowService (which owns the
 * scheduled -> toss -> live setup workflow) — this is a distinct
 * concern: deriving and locking in the match's final outcome once both
 * innings are already done.
 *
 * A won/lost/tied result is always server-derived from Innings.
 * total_runs/total_wickets — never a value the admin/scorer chooses.
 * recordSuperOverResult() is the one deliberate exception: a tied
 * match's Super Over winner is a manual admin/scorer entry, since no
 * ball-by-ball Super Over engine exists (frozen S02 rule 7) — see that
 * method's docblock. reopenMatch() (admin-only) is the sole way to undo
 * an already-finalized result for correction.
 *
 * Still out of scope by design: tournament NRR and DLS. A full
 * ball-by-ball Super Over engine, and abandoned/no-result workflows
 * beyond StandingsService's 1-point-each treatment, remain future work.
 */
class MatchResultService
{
    /**
     * Whether "Finalize Match" may currently be used: the match must
     * still be live, both innings must exist and be completed, their
     * batting/bowling team identities must be internally consistent
     * (opposite sides, both belonging to this match), and a result must
     * actually be safely derivable from their totals.
     */
    public function canFinalize(GameMatch $match): bool
    {
        if ($match->match_status !== 'live') {
            return false;
        }

        $first = $match->firstInnings;
        $second = $match->secondInnings;

        if (! $first || ! $second || $first->status !== 'completed' || $second->status !== 'completed') {
            return false;
        }

        if (! $this->areInningsConsistent($match, $first, $second)) {
            return false;
        }

        return $this->calculateResult($first, $second) !== null;
    }

    /**
     * Pure calculation from two completed innings — no I/O, so the
     * match show page's result preview and finalizeMatch() itself
     * always agree, by construction, on what the result is. Returns
     * null when a result cannot be safely derived (corrupt totals),
     * rather than guessing.
     *
     * @return array{winner_team_id: int|null, result_type: string, win_margin_type: string|null, win_margin: int|null, match_result: string}|null
     */
    public function calculateResult(Innings $first, Innings $second): ?array
    {
        if ((int) $first->total_runs > (int) $second->total_runs) {
            $margin = $first->total_runs - $second->total_runs;

            return [
                'winner_team_id' => $first->batting_team_id,
                'result_type' => 'won',
                'win_margin_type' => 'runs',
                'win_margin' => $margin,
                'match_result' => sprintf('%s won by %d run%s', $first->battingTeam->team->name, $margin, $margin === 1 ? '' : 's'),
            ];
        }

        if ((int) $second->total_runs > (int) $first->total_runs) {
            // A corrupt/impossible wicket count must never produce a
            // negative margin — fail safely instead of guessing.
            if ((int) $second->total_wickets > Innings::MAX_WICKETS) {
                return null;
            }

            $wicketsRemaining = Innings::MAX_WICKETS - $second->total_wickets;

            return [
                'winner_team_id' => $second->batting_team_id,
                'result_type' => 'won',
                'win_margin_type' => 'wickets',
                'win_margin' => $wicketsRemaining,
                'match_result' => sprintf('%s won by %d wicket%s', $second->battingTeam->team->name, $wicketsRemaining, $wicketsRemaining === 1 ? '' : 's'),
            ];
        }

        return [
            'winner_team_id' => null,
            'result_type' => 'tied',
            'win_margin_type' => null,
            'win_margin' => null,
            'match_result' => 'Match tied',
        ];
    }

    /**
     * Locks the match row and both innings rows, rechecks every
     * eligibility condition against fresh data, derives the result, and
     * writes it plus match_status=completed/completed_at atomically. A
     * repeated call after the match is already completed simply fails
     * (match_status is no longer 'live'), leaving the stored result
     * untouched.
     */
    public function finalizeMatch(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            $first = Innings::query()
                ->where('match_id', $locked->id)
                ->where('innings_number', 1)
                ->with(['battingTeam.team', 'bowlingTeam.team'])
                ->lockForUpdate()
                ->first();

            $second = Innings::query()
                ->where('match_id', $locked->id)
                ->where('innings_number', 2)
                ->with(['battingTeam.team', 'bowlingTeam.team'])
                ->lockForUpdate()
                ->first();

            if ($locked->match_status !== 'live'
                || ! $first || ! $second
                || $first->status !== 'completed' || $second->status !== 'completed'
                || ! $this->areInningsConsistent($locked, $first, $second)) {
                return false;
            }

            $result = $this->calculateResult($first, $second);

            if ($result === null) {
                return false;
            }

            $locked->update(array_merge($result, [
                'match_status' => 'completed',
                'completed_at' => now(),
            ]));

            return true;
        });
    }

    /**
     * Both innings must belong to this match, each innings' own
     * batting/bowling teams must differ from each other, both must be
     * one of this match's two participating edition_teams, and the two
     * innings must represent exactly opposite sides (Innings #1's
     * batting team is Innings #2's bowling team, and vice versa) — the
     * same invariant InningsService::startSecondInnings() establishes
     * by construction, re-verified here defensively rather than trusted
     * blindly.
     */
    private function areInningsConsistent(GameMatch $match, Innings $first, Innings $second): bool
    {
        $matchTeamIds = [(int) $match->edition_team_a_id, (int) $match->edition_team_b_id];

        return (int) $first->match_id === (int) $match->id
            && (int) $second->match_id === (int) $match->id
            && in_array((int) $first->batting_team_id, $matchTeamIds, true)
            && in_array((int) $first->bowling_team_id, $matchTeamIds, true)
            && (int) $first->batting_team_id !== (int) $first->bowling_team_id
            && (int) $first->batting_team_id === (int) $second->bowling_team_id
            && (int) $first->bowling_team_id === (int) $second->batting_team_id;
    }

    /**
     * Tied Match / Super Over (frozen S02 rule 7): only ball-by-ball-free
     * — the admin/scorer manually records the Super Over's winner once
     * the two main innings are already completed and tied. The main
     * innings' scores are never touched. A full Super Over scoring
     * engine is explicitly out of scope for this phase.
     */
    public function canRecordSuperOverResult(GameMatch $match): bool
    {
        $first = $match->firstInnings;
        $second = $match->secondInnings;

        if ($match->match_status !== 'live' || ! $first || ! $second
            || $first->status !== 'completed' || $second->status !== 'completed'
            || ! $this->areInningsConsistent($match, $first, $second)) {
            return false;
        }

        $result = $this->calculateResult($first, $second);

        return $result !== null && $result['result_type'] === 'tied';
    }

    /**
     * Finalizes the match with the Super Over's winner: result_type
     * stays 'won' (a winner was genuinely decided) while result_source
     * records HOW, and result_note carries the mandatory reason/note
     * (which may optionally include the Super Over's own score, kept as
     * free text rather than structured columns — a full Super Over
     * scoring engine is later work). No win_margin is fabricated for a
     * Super Over result.
     */
    public function recordSuperOverResult(GameMatch $match, EditionTeam $winner, string $reason, User $performedBy): bool
    {
        return DB::transaction(function () use ($match, $winner, $reason, $performedBy) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canRecordSuperOverResult($locked)) {
                return false;
            }

            $participatingTeamIds = [(int) $locked->edition_team_a_id, (int) $locked->edition_team_b_id];

            if (! in_array((int) $winner->id, $participatingTeamIds, true)) {
                throw ValidationException::withMessages([
                    'winner_team_id' => 'The Super Over winner must be one of the two teams in this match.',
                ]);
            }

            $winner->loadMissing('team');

            $locked->update([
                'winner_team_id' => $winner->id,
                'result_type' => 'won',
                'result_source' => 'super_over',
                'result_note' => $reason,
                'win_margin_type' => null,
                'win_margin' => null,
                'match_result' => "Match tied — {$winner->team->name} won the Super Over.",
                'match_status' => 'completed',
                'completed_at' => now(),
            ]);

            ScoringEvent::create([
                'match_id' => $locked->id,
                'type' => ScoringEvent::TYPE_SUPER_OVER_RESULT,
                'awarded_team_id' => $winner->id,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
            ]);

            return true;
        });
    }

    /**
     * Reopen a finalized match (frozen S02 rule 17): ADMIN ONLY (see
     * GameMatchPolicy::reopenResult()). Clears the previously-derived
     * result so the admin can reopen/correct the relevant innings via
     * InningsService::reopenInnings(), re-record or undo deliveries, and
     * then re-run finalizeMatch() (or recordSuperOverResult()) as
     * normal. Standings/stats need no separate update step — both are
     * always derived fresh from current GameMatch/Innings state, never
     * cached.
     */
    public function canReopenMatch(GameMatch $match): bool
    {
        return $match->match_status === 'completed';
    }

    public function reopenMatch(GameMatch $match, string $reason, User $performedBy): bool
    {
        return DB::transaction(function () use ($match, $reason, $performedBy) {
            $locked = GameMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $this->canReopenMatch($locked)) {
                return false;
            }

            ScoringEvent::create([
                'match_id' => $locked->id,
                'type' => ScoringEvent::TYPE_MATCH_RESULT_REOPENED,
                'reason' => $reason,
                'performed_by' => $performedBy->id,
                'payload' => [
                    'previous_result_type' => $locked->result_type,
                    'previous_winner_team_id' => $locked->winner_team_id,
                ],
            ]);

            return (bool) $locked->update([
                'match_status' => 'live',
                'winner_team_id' => null,
                'result_type' => null,
                'result_source' => null,
                'result_note' => null,
                'win_margin_type' => null,
                'win_margin' => null,
                'match_result' => null,
                'completed_at' => null,
                // Clearing these two lets a corrected re-finalize send a
                // FRESH result notification with the corrected outcome,
                // rather than staying permanently marked as already
                // notified for a result that no longer exists.
                'result_notification_dispatched_at' => null,
                'result_notification_id' => null,
            ]);
        });
    }
}
