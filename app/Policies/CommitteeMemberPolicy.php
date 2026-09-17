<?php

namespace App\Policies;

use App\Models\CommitteeMember;
use App\Models\User;

/**
 * Resource-specific authorization for committee member management.
 * Mirrors VenuePolicy/EditionTransactionPolicy: only the admin role may
 * manage committee members — scorers never get any of these abilities.
 */
class CommitteeMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, CommitteeMember $committeeMember): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, CommitteeMember $committeeMember): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, CommitteeMember $committeeMember): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
