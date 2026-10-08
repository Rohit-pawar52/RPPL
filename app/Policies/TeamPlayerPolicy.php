<?php

namespace App\Policies;

use App\Models\TeamPlayer;
use App\Models\User;

/**
 * Resource-specific authorization for Squad (TeamPlayer) management.
 * Squads follow the teams permissions, like TeamPolicy: `teams.view` lets
 * a role look at squads, `teams.manage` lets it add, change and remove
 * squad players. The admin role holds both; a scorer holds neither.
 */
class TeamPlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('teams.view');
    }

    public function view(User $user, TeamPlayer $teamPlayer): bool
    {
        return $user->hasPermission('teams.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('teams.manage');
    }

    public function update(User $user, TeamPlayer $teamPlayer): bool
    {
        return $user->hasPermission('teams.manage');
    }

    public function delete(User $user, TeamPlayer $teamPlayer): bool
    {
        return $user->hasPermission('teams.manage');
    }
}
