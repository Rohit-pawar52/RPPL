<?php

namespace App\Policies;

use App\Models\Edition;
use App\Models\User;

/**
 * Resource-specific authorization for Edition management, distinct from
 * the broad "access-admin-panel" Gate:
 * that Gate decides whether a role may enter the admin app at all;
 * this Policy decides whether a role may act on the Edition resource
 * itself. What a role may do is stored per role (see
 * App\Support\Permissions): `editions.view` lets it look, and
 * `editions.manage` lets it create, update and delete (it includes
 * viewing). The admin role holds both; a scorer holds neither.
 */
class EditionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('editions.view');
    }

    public function view(User $user, Edition $edition): bool
    {
        return $user->hasPermission('editions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('editions.manage');
    }

    public function update(User $user, Edition $edition): bool
    {
        return $user->hasPermission('editions.manage');
    }

    public function delete(User $user, Edition $edition): bool
    {
        return $user->hasPermission('editions.manage');
    }
}
