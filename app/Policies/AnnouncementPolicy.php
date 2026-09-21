<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Resource-specific authorization for Announcement management (Phase
 * 3.45). Mirrors VenuePolicy/TeamPolicy/PlayerPolicy: only the admin
 * role may manage announcements — scorers never get any of these
 * abilities.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
