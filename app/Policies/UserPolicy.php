<?php

namespace App\Policies;

use App\Models\User;

/**
 * Resource-specific authorization for application login account
 * management. Admin only — a scorer never gets any of these abilities.
 * No delete ability exists: accounts are deactivated, never deleted
 * (see UserController's lack of a destroy route).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
