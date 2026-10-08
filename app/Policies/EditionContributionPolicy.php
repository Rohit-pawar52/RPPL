<?php

namespace App\Policies;

use App\Models\EditionContribution;
use App\Models\User;

/**
 * Resource-specific authorization for the committee contribution ledger. Looking at it takes the
 * `finance.view` permission; recording or deleting a contribution takes `finance.manage`. No
 * update ability exists because contributions are never edited, only recorded or deleted (see
 * EditionContributionController/Service).
 */
class EditionContributionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, EditionContribution $editionContribution): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.manage');
    }

    public function delete(User $user, EditionContribution $editionContribution): bool
    {
        return $user->hasPermission('finance.manage');
    }
}
