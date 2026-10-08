<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Who may look at and edit roles (and so decide what every other role may
 * do). `roles.view` / `roles.manage` are access-control permissions that are
 * never delegable (see App\Support\Permissions), so in practice only the
 * admin role passes - no slug check is needed here, and none must be added:
 * the permission is the single rule, and a role that is handed one by
 * mistake still cannot use it.
 *
 * Two limits concern the role being acted on, not the person acting: the
 * admin role always holds every permission, so it cannot be edited down; and
 * the built-in roles (admin, scorer, auctioneer) are referred to by slug in
 * code, so they cannot be deleted.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && ! $role->isAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && ! $role->isSystem();
    }
}
