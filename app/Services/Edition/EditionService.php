<?php

namespace App\Services\Edition;

use App\Models\Edition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditionService
{
    public function createEdition(array $data): Edition
    {
        return Edition::create($data);
    }

    /**
     * Applies the one status-lifecycle rule this project currently has:
     * a completed edition cannot be moved back to another status. No
     * other transition is restricted — the enum has no other previously
     * defined workflow to enforce (see the Phase 3.2 report).
     */
    public function updateEdition(Edition $edition, array $data): Edition
    {
        $newStatus = $data['status'] ?? $edition->status;

        if ($edition->status === 'completed' && $newStatus !== 'completed') {
            throw ValidationException::withMessages([
                'status' => 'A completed edition cannot be moved back to another status.',
            ]);
        }

        $edition->update($data);

        return $edition;
    }

    /**
     * Deletes an edition only when it owns no tournament data yet.
     * Returns false (rather than letting a foreign-key constraint fail)
     * when the edition is not eligible, so the controller can show a
     * friendly message instead of a raw database error.
     *
     * Wrapped in a transaction with a row lock so a concurrent request
     * cannot create dependent data between the eligibility check and
     * the delete itself.
     */
    public function deleteEdition(Edition $edition): bool
    {
        return DB::transaction(function () use ($edition) {
            $locked = Edition::whereKey($edition->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasTournamentData($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    public function hasTournamentData(Edition $edition): bool
    {
        return $edition->playerRegistrations()->exists()
            || $edition->editionTeams()->exists()
            || $edition->matches()->exists();
    }
}
