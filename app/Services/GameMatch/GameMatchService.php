<?php

namespace App\Services\GameMatch;

use App\Models\GameMatch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameMatchService
{
    /**
     * StoreGameMatchRequest already validates edition eligibility, team
     * eligibility/consistency, and match_number uniqueness before this
     * runs, so a single INSERT needs no transaction. The database's own
     * UNIQUE(edition_id, match_number) constraint remains the final
     * guarantee against a genuine race — caught here only to turn that
     * rare case into a friendly message instead of a raw SQL error.
     */
    public function createMatch(array $data): GameMatch
    {
        try {
            return GameMatch::create($data);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'match_number' => 'This match number is already used in the selected edition.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * edition_id/edition_team_a_id/edition_team_b_id are only present in
     * $data when canChangeFixtureIdentity() allowed them through
     * UpdateGameMatchRequest — see its docblock. Scheduling metadata
     * (venue, stage, overs, scheduled_at, match_number) may always be
     * updated regardless of match history.
     */
    public function updateMatch(GameMatch $match, array $data): GameMatch
    {
        try {
            $match->update($data);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'match_number' => 'This match number is already used in the selected edition.',
                ]);
            }

            throw $e;
        }

        return $match;
    }

    /**
     * Deletes a match only when it has no recorded squad/scoring data.
     * Returns false instead of letting an FK constraint fail, so the
     * controller can show a friendly message.
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): MatchPlayerController::store() and
     * InningsController::startFirst()/startSecond() are live admin write
     * paths, and match_players.match_id/innings.match_id (and, in turn,
     * deliveries.innings_id) all cascade-delete on GameMatch deletion —
     * without this lock, a concurrent Playing XI selection or innings
     * start between an unlocked check and the delete would have its
     * rows (including any already-recorded Delivery history) silently
     * destroyed instead of blocking the deletion.
     */
    public function deleteMatch(GameMatch $match): bool
    {
        return DB::transaction(function () use ($match) {
            $locked = GameMatch::whereKey($match->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasMatchHistory($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    public function hasMatchHistory(GameMatch $match): bool
    {
        return $match->matchPlayers()->exists() || $match->innings()->exists();
    }

    /**
     * Whether edition_id/edition_team_a_id/edition_team_b_id may still
     * be changed. Once any MatchPlayer (squad selection) or Innings
     * exists for this match, its identity is locked — changing which
     * teams or edition a match belongs to at that point would corrupt
     * already-recorded squad/scoring data.
     */
    public function canChangeFixtureIdentity(GameMatch $match): bool
    {
        return ! $this->hasMatchHistory($match);
    }
}
