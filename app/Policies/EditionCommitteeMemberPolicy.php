<?php

namespace App\Policies;

use App\Models\EditionCommitteeMember;
use App\Models\User;

/**
 * Resource-specific authorization for edition committee membership management (Phase 3.48). Like
 * CommitteeMemberPolicy, looking takes the `committee.view` permission and adding or removing a
 * member takes `committee.manage`. There is no update ability: a membership is added or removed,
 * never edited.
 */
class EditionCommitteeMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('committee.view');
    }

    public function view(User $user, EditionCommitteeMember $editionCommitteeMember): bool
    {
        return $user->hasPermission('committee.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('committee.manage');
    }

    public function delete(User $user, EditionCommitteeMember $editionCommitteeMember): bool
    {
        return $user->hasPermission('committee.manage');
    }
}
