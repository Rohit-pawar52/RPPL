<?php

namespace App\Policies;

use App\Models\CommitteeMember;
use App\Models\User;

/**
 * Resource-specific authorization for committee member management. Looking at members takes the
 * `committee.view` permission; adding, changing or deleting one takes `committee.manage` (which
 * includes viewing).
 */
class CommitteeMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('committee.view');
    }

    public function view(User $user, CommitteeMember $committeeMember): bool
    {
        return $user->hasPermission('committee.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('committee.manage');
    }

    public function update(User $user, CommitteeMember $committeeMember): bool
    {
        return $user->hasPermission('committee.manage');
    }

    public function delete(User $user, CommitteeMember $committeeMember): bool
    {
        return $user->hasPermission('committee.manage');
    }
}
