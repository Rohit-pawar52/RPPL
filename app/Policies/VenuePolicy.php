<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

/**
 * Resource-specific authorization for Venue management. Mirrors
 * TeamPolicy/PlayerPolicy/EditionPolicy: `venues.view` lets a role look
 * at venues, `venues.manage` lets it create, update and delete them (it
 * includes viewing). The admin role holds both; a scorer holds neither.
 */
class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('venues.view');
    }

    public function view(User $user, Venue $venue): bool
    {
        return $user->hasPermission('venues.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('venues.manage');
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->hasPermission('venues.manage');
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->hasPermission('venues.manage');
    }
}
