<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

/**
 * Resource-specific authorization for Player management. Mirrors
 * EditionPolicy: `players.view` lets a role look at players,
 * `players.manage` lets it create, update and delete them (it includes
 * viewing). The admin role holds both; a scorer holds neither.
 */
class PlayerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('players.view');
    }

    public function view(User $user, Player $player): bool
    {
        return $user->hasPermission('players.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('players.manage');
    }

    public function update(User $user, Player $player): bool
    {
        return $user->hasPermission('players.manage');
    }

    public function delete(User $user, Player $player): bool
    {
        return $user->hasPermission('players.manage');
    }
}
