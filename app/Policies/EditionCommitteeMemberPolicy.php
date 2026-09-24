<?php

namespace App\Policies;

use App\Models\EditionCommitteeMember;
use App\Models\User;

/**
 * Resource-specific authorization for edition committee membership
 * management (Phase 3.48). Admin only, matching every other Finance-area
 * policy (CommitteeMemberPolicy/ContributorPolicy/EditionContributionPolicy/
 * EditionTransactionPolicy) — this phase does not change scorer access
 * to Finance in any way.
 */
class EditionCommitteeMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, EditionCommitteeMember $editionCommitteeMember): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, EditionCommitteeMember $editionCommitteeMember): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
