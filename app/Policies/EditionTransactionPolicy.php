<?php

namespace App\Policies;

use App\Models\EditionTransaction;
use App\Models\User;

/**
 * Resource-specific authorization for the edition finance ledger.
 * Mirrors VenuePolicy/TeamPolicy/PlayerPolicy: only the admin role may
 * manage transactions — scorers never get any of these abilities.
 */
class EditionTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, EditionTransaction $editionTransaction): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, EditionTransaction $editionTransaction): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, EditionTransaction $editionTransaction): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
