<?php

namespace App\Policies;

use App\Models\EditionTransaction;
use App\Models\User;

/**
 * Resource-specific authorization for the edition finance ledger. Looking at it takes the
 * `finance.view` permission; recording, changing or deleting a transaction takes `finance.manage`
 * (which includes viewing). What each role holds is stored per role - see App\Support\Permissions.
 */
class EditionTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, EditionTransaction $editionTransaction): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.manage');
    }

    public function update(User $user, EditionTransaction $editionTransaction): bool
    {
        return $user->hasPermission('finance.manage');
    }

    public function delete(User $user, EditionTransaction $editionTransaction): bool
    {
        return $user->hasPermission('finance.manage');
    }
}
