<?php

namespace App\Services\Venue;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class VenueService
{
    public function createVenue(array $data): Venue
    {
        return Venue::create($data);
    }

    public function updateVenue(Venue $venue, array $data): Venue
    {
        $venue->update($data);

        return $venue;
    }

    /**
     * Deletes a venue only when it has no match history. Returns false
     * instead of relying on the nullOnDelete() FK to silently detach
     * historical matches — a venue referenced by any match (scheduled
     * or completed) must be preserved, not just "allowed to go null".
     *
     * Wrapped in a transaction with a row lock, mirroring
     * PlayerService::deletePlayer(): GameMatchController::store() is a
     * live admin write path, so a concurrent match creation between an
     * unlocked check and the delete could otherwise slip through.
     */
    public function deleteVenue(Venue $venue): bool
    {
        return DB::transaction(function () use ($venue) {
            $locked = Venue::whereKey($venue->getKey())->lockForUpdate()->firstOrFail();

            if ($this->hasMatchHistory($locked)) {
                return false;
            }

            return (bool) $locked->delete();
        });
    }

    public function hasMatchHistory(Venue $venue): bool
    {
        return $venue->matches()->exists();
    }
}
