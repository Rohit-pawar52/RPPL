<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Resource-specific authorization for Announcement management (Phase 3.45). Looking at the
 * announcements takes the `announcements.view` permission; adding, changing or deleting one takes
 * `announcements.manage` (which includes viewing).
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('announcements.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('announcements.manage');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission('announcements.manage');
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->hasPermission('announcements.manage');
    }
}
