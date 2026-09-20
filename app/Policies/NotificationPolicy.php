<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Resource-specific authorization for admin notification-content
 * management (Phase B3). Admin only — a scorer never gets any of these
 * abilities, matching every other tournament-management resource. No
 * delete ability exists: NotificationController has no destroy() route
 * (a broadcast's content is never deleted once authored — see its
 * docblock).
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Notification $notification): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return $user->role?->slug === 'admin';
    }
}
