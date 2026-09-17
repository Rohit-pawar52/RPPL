<?php

namespace App\Policies;

use App\Models\PlayerRegistration;
use App\Models\User;

/**
 * Resource-specific authorization for Player Registration management.
 * Mirrors EditionPolicy/PlayerPolicy: only the admin role may manage
 * registrations — scorers never get any of these abilities.
 */
class PlayerRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, PlayerRegistration $playerRegistration): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, PlayerRegistration $playerRegistration): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, PlayerRegistration $playerRegistration): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
