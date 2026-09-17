<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

/**
 * Resource-specific authorization for Player management. Mirrors
 * EditionPolicy: only the admin role may manage players — scorers never
 * get any of these abilities.
 */
class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Player $player): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Player $player): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Player $player): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
