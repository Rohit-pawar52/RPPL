<?php

namespace App\Policies;

use App\Models\EditionContribution;
use App\Models\User;

/**
 * Resource-specific authorization for the committee contribution
 * ledger. Admin only — no update ability exists because contributions
 * are never edited, only recorded or deleted (see
 * EditionContributionController/Service).
 */
class EditionContributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, EditionContribution $editionContribution): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, EditionContribution $editionContribution): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
