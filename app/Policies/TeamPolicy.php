<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * Resource-specific authorization for Team management. Mirrors
 * PlayerPolicy/EditionPolicy: only the admin role may manage teams —
 * scorers never get any of these abilities.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Team $team): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Team $team): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
