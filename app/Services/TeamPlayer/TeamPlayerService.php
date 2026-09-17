<?php

namespace App\Services\TeamPlayer;

use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamPlayerService
{
    /**
     * StoreTeamPlayerRequest already validates the same-edition
     * consistency rule (the most important rule of this phase), active
     * team/player, non-completed edition, and the duplicate-
     * registration/jersey checks. This defensively re-verifies the
     * same-edition rule directly against fresh data before writing,
     * since it is the one rule this project was explicitly asked to
     * stop deferring — cheap insurance against it ever being bypassed
     * by a future caller of this service that skips the FormRequest.
     */
    public function createTeamPlayer(array $data): TeamPlayer
    {
        $editionTeam = EditionTeam::findOrFail($data['edition_team_id']);
        $registration = PlayerRegistration::findOrFail($data['player_registration_id']);

        if ($registration->edition_id !== $editionTeam->edition_id) {
            throw ValidationException::withMessages([
                'player_registration_id' => 'The selected player is not registered for the same edition as the selected team.',
            ]);
        }

        try {
            return TeamPlayer::create($data);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'player_registration_id' => 'This player is already assigned to a squad, or the jersey number is already taken on this team.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * edition_team_id/player_registration_id are never part of $data
     * here — see UpdateTeamPlayerRequest, which only validates squad
     * metadata (jersey_number, role). A squad assignment's identity is
     * immutable after creation.
     */
    public function updateTeamPlayer(TeamPlayer $teamPlayer, array $data): TeamPlayer
    {
        $teamPlayer->update($data);

        return $teamPlayer;
    }

    /**
     * Deletes a squad assignment only when it has never been used in a
     * match. Returns false instead of letting the FK constraint fail,
     * so the controller can show a friendly message.
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): MatchPlayerController::store() is a
     * live admin write path, and match_players.team_player_id
     * cascade-deletes on TeamPlayer deletion, so a concurrent Playing XI
     * selection between an unlocked check and the delete would be
     * silently destroyed.
     */
    public function deleteTeamPlayer(TeamPlayer $teamPlayer): bool
    {
        return DB::transaction(function () use ($teamPlayer) {
            $locked = TeamPlayer::whereKey($teamPlayer->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasMatchHistory($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    public function hasMatchHistory(TeamPlayer $teamPlayer): bool
    {
        return $teamPlayer->matchPlayers()->exists();
    }
}
