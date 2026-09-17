<?php

namespace App\Services\EditionTeam;

use App\Models\EditionTeam;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditionTeamService
{
    /**
     * StoreEditionTeamRequest already checks eligibility (active team,
     * non-completed edition, no existing edition+team pair) before this
     * runs, so a single INSERT needs no transaction. The database's own
     * UNIQUE(edition_id, team_id) constraint remains the final guarantee
     * against a genuine race between two concurrent requests for the
     * same pair — caught here only to turn that rare case into the same
     * friendly message instead of a raw SQL error (mirrors
     * PlayerRegistrationService::createRegistration()).
     */
    public function createEditionTeam(array $data): EditionTeam
    {
        try {
            return EditionTeam::create($data);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'team_id' => 'This team is already participating in the selected edition.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Deletes a team's participation in an edition only when no
     * tournament data has been built on top of it yet: no squad
     * (TeamPlayer), no match (as either side), no innings (as either
     * batting or bowling team). Returns false instead of letting an FK
     * constraint fail, so the controller can show a friendly message.
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): TeamPlayerController::store() is a
     * live admin write path, and team_players.edition_team_id
     * cascade-deletes on EditionTeam deletion, so a concurrent squad
     * addition between an unlocked check and the delete would be
     * silently destroyed.
     */
    public function deleteEditionTeam(EditionTeam $editionTeam): bool
    {
        return DB::transaction(function () use ($editionTeam) {
            $locked = EditionTeam::whereKey($editionTeam->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasTournamentUsage($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    /**
     * toss_winner_team_id/winner_team_id on matches are intentionally
     * not checked separately here: for any match created through this
     * application's own logic, a team can only win a toss or a match it
     * is actually playing in, so those columns are always a subset of
     * edition_team_a_id/edition_team_b_id. (The database itself does
     * not enforce that invariant — a known deferred validation rule
     * from the Phase 1 review — but no admin-writable path exists yet
     * that could violate it.)
     */
    public function hasTournamentUsage(EditionTeam $editionTeam): bool
    {
        return $editionTeam->teamPlayers()->exists()
            || $editionTeam->matchesAsTeamA()->exists()
            || $editionTeam->matchesAsTeamB()->exists()
            || $editionTeam->inningsAsBattingTeam()->exists()
            || $editionTeam->inningsAsBowlingTeam()->exists();
    }
}
