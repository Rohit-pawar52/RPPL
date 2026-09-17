<?php

namespace App\Policies;

use App\Models\Contributor;
use App\Models\User;

/**
 * Resource-specific authorization for general contributor management.
 * Mirrors CommitteeMemberPolicy exactly: only the admin role may manage
 * contributors — scorers never get any of these abilities.
 */
class ContributorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Contributor $contributor): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Contributor $contributor): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Contributor $contributor): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
