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
     * squad. Returns false instead of letting the FK constraint fail,
     * so the controller can show a friendly message.
     *
     * No lockForUpdate() here: squad assignment (TeamPlayer) has no
     * admin UI yet in this phase, so there is no real concurrent writer
     * that could race this check — adding a lock now would guard
     * against a scenario that cannot currently happen.
     *
     * File cleanup (Phase 3.39D) happens strictly AFTER the DB row is
     * confirmed deleted, never before: if delete() somehow returns
     * false, the registration survives and its documents must too. A
     * null path (every manual/CSV registration, and any historical row)
     * is simply skipped — never passed to Storage::delete().
     */
    public function deleteRegistration(PlayerRegistration $registration): bool
    {
        if ($this->hasSquadAssignment($registration)) {
            return false;
        }

        $ownedPaths = array_filter([
            $registration->aadhaar_document_path,
            $registration->payment_proof_path,
        ]);

        if (! $registration->delete()) {
            return false;
        }

        if ($ownedPaths !== []) {
            Storage::disk('local')->delete($ownedPaths);
        }

        return true;
    }

    public function hasSquadAssignment(PlayerRegistration $registration): bool
    {
        return $registration->teamPlayer()->exists();
    }
}
