<?php

namespace App\Policies;

use App\Models\User;

/**
 * Resource-specific authorization for application login account
 * management. `users.view` lets a role look at accounts, `users.manage`
 * lets it create and edit them. Both are access-control permissions that
 * are never delegable (see App\Support\Permissions): only the admin role
 * holds them, because whoever may create accounts or pick their role could
 * otherwise hand out full control.
 * No delete ability exists: accounts are deactivated, never deleted
 * (see UserController's lack of a destroy route).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.manage');
    }
}
