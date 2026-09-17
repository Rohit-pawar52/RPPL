<?php

namespace App\Policies;

use App\Models\Edition;
use App\Models\User;

/**
 * Resource-specific authorization for Edition management, distinct from
 * the broad "access-admin-panel" / "manage-tournament" Gates:
 * Gates decide whether a role may enter a whole area of the admin app;
 * this Policy decides whether a role may act on the Edition resource
 * itself. Only the admin role may manage editions — scorers never get
 * any of these abilities.
 */
class EditionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Edition $edition): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Edition $edition): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Edition $edition): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
