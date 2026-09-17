<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

/**
 * Resource-specific authorization for Venue management. Mirrors
 * TeamPolicy/PlayerPolicy/EditionPolicy: only the admin role may manage
 * venues — scorers never get any of these abilities.
 */
class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Venue $venue): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Venue $venue): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
