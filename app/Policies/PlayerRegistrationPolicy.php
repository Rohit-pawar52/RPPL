<?php

namespace App\Policies;

use App\Models\PlayerRegistration;
use App\Models\User;

/**
 * Resource-specific authorization for Player Registration management.
 * Mirrors EditionPolicy/PlayerPolicy: `registrations.view` lets a role
 * look at registrations (and their private documents),
 * `registrations.manage` lets it add, import, edit, verify payment of and
 * delete them (it includes viewing). The admin role holds both; a scorer
 * holds neither.
 */
class PlayerRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('registrations.view');
    }

    public function view(User $user, PlayerRegistration $playerRegistration): bool
    {
        return $user->hasPermission('registrations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('registrations.manage');
    }

    public function update(User $user, PlayerRegistration $playerRegistration): bool
    {
        return $user->hasPermission('registrations.manage');
    }

    public function delete(User $user, PlayerRegistration $playerRegistration): bool
    {
        return $user->hasPermission('registrations.manage');
    }
}
