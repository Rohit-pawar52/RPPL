<?php

namespace App\Services\PlayerRegistration;

use App\Models\PlayerRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlayerRegistrationService
{
    /**
     * StorePlayerRegistrationRequest already checks eligibility (active
     * player, non-completed edition, no existing edition+player pair)
     * before this runs. The database's own UNIQUE(edition_id, player_id)
     * constraint remains the final guarantee against a genuine race
     * between two concurrent requests for the same pair — caught here
     * only to turn that rare case into the same friendly message
     * instead of a raw SQL error.
     *
     * registration_number is NOT NULL + UNIQUE and deliberately excluded
     * from $fillable, so it can never arrive via mass-assignment; the
     * row is inserted with a throwaway unique placeholder purely to
     * satisfy that constraint, then immediately overwritten with the
     * real RPPL-{year}-{id} value by assignRegistrationNumber() — the
     * one shared mechanism every creation path (here, CSV import, and
     * the future guest registration) uses, so the format never drifts.
     * Wrapped in a transaction so a failure between the two writes
     * cannot leave a row stuck with its placeholder value.
     */
    public function createRegistration(array $data): PlayerRegistration
    {
        try {
            return DB::transaction(function () use ($data) {
                $registration = new PlayerRegistration($data);
                $registration->registration_number = (string) Str::uuid();
                $registration->save();

                return $registration->assignRegistrationNumber();
            });
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                throw ValidationException::withMessages([
                    'player_id' => 'This player is already registered for the selected edition.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * edition_id/player_id are never part of $data here — see
     * UpdatePlayerRegistrationRequest, which only validates payment
     * metadata. A registration's identity is immutable after creation.
     */
    public function updateRegistration(PlayerRegistration $registration, array $data): PlayerRegistration
    {
        $registration->update($data);

        return $registration;
    }

    /**
     * Deletes a registration only when it has not been assigned to a
     * squad. Returns false instead of letting the delete silently
     * cascade, so the controller can show a friendly message.
     *
     * Locks the registration row and rechecks hasSquadAssignment()
     * against fresh data before writing (Phase 3.41), mirroring
     * TeamPlayerService::deleteTeamPlayer()'s exact pattern for the
     * same class of race. This matters here specifically because
     * team_players.player_registration_id is CASCADE ON DELETE (see the
     * team_players migration) — unlike a RESTRICT constraint, an
     * unguarded delete() would not fail loudly on a squad-assigned
     * registration, it would silently destroy the TeamPlayer row too.
     * A bare "check then delete" without a lock leaves a window where a
     * concurrent TeamPlayerController::store() could create that squad
     * assignment between the check and the delete.
     *
     * No corresponding change was needed on the TeamPlayer creation
     * side: TeamPlayer::create() inserts a row with a foreign key to
     * this same player_registrations row, and InnoDB's FK-check
     * mechanism takes an implicit shared lock on the referenced parent
     * row for the life of that insert's transaction — the same row this
     * lockForUpdate() call holds exclusively. The two writers are
     * already forced to serialize through that shared parent row,
     * without TeamPlayer's own code needing to participate explicitly.
     *
     * File cleanup (Phase 3.39D) happens strictly AFTER the DB row is
     * confirmed deleted, never before: if delete() somehow returns
     * false, the registration survives and its documents must too. A
     * null path (every manual/CSV registration, and any historical row)
     * is simply skipped — never passed to Storage::delete().
     */
    public function deleteRegistration(PlayerRegistration $registration): bool
    {
        return DB::transaction(function () use ($registration) {
            $locked = PlayerRegistration::whereKey($registration->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasSquadAssignment($locked)) {
                return false;
            }

            $ownedPaths = array_filter([
                $locked->aadhaar_document_path,
                $locked->payment_proof_path,
            ]);

            if (! $locked->delete()) {
                return false;
            }

            if ($ownedPaths !== []) {
                Storage::disk('local')->delete($ownedPaths);
            }

            return true;
        });
    }

    public function hasSquadAssignment(PlayerRegistration $registration): bool
    {
        return $registration->teamPlayer()->exists();
    }
}
