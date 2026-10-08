<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Resource-specific authorization for admin notification-content management (Phase B3). Looking
 * takes the `notifications.view` permission, writing or editing a notification takes
 * `notifications.manage` (which includes viewing), and broadcasting one takes its own
 * `notifications.send` - so a role can be allowed to draft without being allowed to send. No
 * delete ability exists: NotificationController has no destroy() route (a broadcast's content is
 * never deleted once authored - see its docblock).
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('notifications.view');
    }

    public function view(User $user, Notification $notification): bool
    {
        return $user->hasPermission('notifications.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('notifications.manage');
    }

    public function update(User $user, Notification $notification): bool
    {
        return $user->hasPermission('notifications.manage');
    }

    /**
     * Send/Resend (Phase B4). Deliberately a permission of its own rather than part of
     * `notifications.manage`: a broadcast reaches every subscribed visitor and cannot be recalled.
     */
    public function send(User $user, Notification $notification): bool
    {
        return $user->hasPermission('notifications.send');
    }
}
