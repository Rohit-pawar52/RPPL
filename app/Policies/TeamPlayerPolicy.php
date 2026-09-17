<?php

namespace App\Policies;

use App\Models\TeamPlayer;
use App\Models\User;

/**
 * Resource-specific authorization for Squad (TeamPlayer) management.
 * Mirrors the other admin policies: only the admin role may manage
 * squads — scorers never get any of these abilities.
 */
class TeamPlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, TeamPlayer $teamPlayer): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, TeamPlayer $teamPlayer): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, TeamPlayer $teamPlayer): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
