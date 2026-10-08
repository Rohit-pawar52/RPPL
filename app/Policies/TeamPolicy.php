<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

/**
 * Resource-specific authorization for Team management. Mirrors
 * PlayerPolicy/EditionPolicy: `teams.view` lets a role look at teams,
 * `teams.manage` lets it create, update and delete them (it includes
 * viewing). The admin role holds both; a scorer holds neither.
 */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('teams.view');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->hasPermission('teams.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('teams.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasPermission('teams.manage');
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasPermission('teams.manage');
    }
}
