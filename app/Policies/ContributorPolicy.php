<?php

namespace App\Policies;

use App\Models\Contributor;
use App\Models\User;

/**
 * Resource-specific authorization for general contributor management. Looking at contributors
 * takes the `contributors.view` permission; adding, changing or deleting one takes
 * `contributors.manage` (which includes viewing).
 */
class ContributorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contributors.view');
    }

    public function view(User $user, Contributor $contributor): bool
    {
        return $user->hasPermission('contributors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contributors.manage');
    }

    public function update(User $user, Contributor $contributor): bool
    {
        return $user->hasPermission('contributors.manage');
    }

    public function delete(User $user, Contributor $contributor): bool
    {
        return $user->hasPermission('contributors.manage');
    }
}
