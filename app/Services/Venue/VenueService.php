<?php

namespace App\Services\Venue;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class VenueService
{
    public function createVenue(array $data): Venue
    {
        return DB::transaction(function () use ($data) {
            $venue = Venue::create($this->settleDefault($data));

            $this->makeOnlyDefault($venue);

            return $venue;
        });
    }

    public function updateVenue(Venue $venue, array $data): Venue
    {
        return DB::transaction(function () use ($venue, $data) {
            $venue->update($this->settleDefault($data));

            $this->makeOnlyDefault($venue);

            return $venue;
        });
    }

    /**
     * An inactive venue is not offered for new matches, so it cannot be
     * the default.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function settleDefault(array $data): array
    {
        if (($data['is_active'] ?? true) == false) {
            $data['is_default'] = false;
        }

        return $data;
    }

    /**
     * At most one default venue: choosing this one clears the others.
     */
    private function makeOnlyDefault(Venue $venue): void
    {
        if ($venue->is_default) {
            Venue::where('id', '!=', $venue->id)->where('is_default', true)->update(['is_default' => false]);
        }
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
