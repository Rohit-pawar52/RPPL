<?php

namespace App\Services\Notification;

use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bulk retention/cleanup for the three notification-domain tables that
 * grow unboundedly over time with no other pruning mechanism —
 * particularly fcm_tokens, which accumulates a row for every device
 * that ever subscribed, including ones that later cleared site data or
 * uninstalled without ever formally unsubscribing (RPPL has no
 * unsubscribe endpoint — see FcmTokenSubscriptionService). An admin
 * triggers each operation explicitly; nothing here runs automatically.
 *
 * Every delete*() method has a matching count*() method using the
 * EXACT same criteria, so a preview shown to the admin can never drift
 * from what the delete actually removes — the controller calls
 * count*() for the preview and delete*() for the real action
 * separately, never trusting a number the browser sends back.
 */
class NotificationDataCleanupService
{
    public function countNotificationsBefore(Carbon $before): int
    {
        return Notification::where('created_at', '<', $before)->count();
    }

    /**
     * Deletes every Notification created before $before, along with its
     * NotificationSend history first — notification_sends.notification_id
     * is a RESTRICT foreign key (see the create_notification_sends_table
     * migration), so the parent row can never be deleted while a send
     * still references it. Both deletes happen in one transaction so a
     * notification is never left dangling with (or without) its history
     * inconsistently.
     *
     * @return int number of Notification rows deleted
     */
    public function deleteNotificationsBefore(Carbon $before): int
    {
        return DB::transaction(function () use ($before) {
            $ids = Notification::where('created_at', '<', $before)->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            NotificationSend::whereIn('notification_id', $ids)->delete();

            return Notification::whereIn('id', $ids)->delete();
        });
    }

    public function countNotificationSendsBefore(Carbon $before): int
    {
        return NotificationSend::where('created_at', '<', $before)->count();
    }

    /**
     * Deletes NotificationSend history rows older than $before,
     * independently of their parent Notification (which is left
     * untouched either way — only deleteNotificationsBefore() above
     * ever removes a Notification itself).
     *
     * @return int number of NotificationSend rows deleted
     */
    public function deleteNotificationSendsBefore(Carbon $before): int
    {
        return NotificationSend::where('created_at', '<', $before)->delete();
    }

    public function countInactiveFcmTokens(): int
    {
        return FcmToken::where('is_active', false)->count();
    }

    /**
     * Every token Firebase has already told us is permanently invalid/
     * unregistered (see SendNotificationJob, the only writer that ever
     * flips is_active to false, based on Firebase's own
     * "invalid_tokens" response) — these can never receive a
     * notification again, so there is no meaningful reason to keep them.
     *
     * @return int number of FcmToken rows deleted
     */
    public function deleteInactiveFcmTokens(): int
    {
        return FcmToken::where('is_active', false)->delete();
    }

    public function countStaleFcmTokens(Carbon $before): int
    {
        return FcmToken::where('last_seen_at', '<', $before)->count();
    }

    /**
     * Tokens not seen (no delivery attempt/heartbeat) since $before —
     * last_seen_at is the only field this schema has that represents
     * genuine last-activity (see FcmToken's own docblock); this
     * deliberately does NOT touch is_active, so an active-but-quiet
     * token past the threshold is still removed regardless of its
     * status, and a recently-active token is kept regardless of status.
     *
     * @return int number of FcmToken rows deleted
     */
    public function deleteStaleFcmTokens(Carbon $before): int
    {
        return FcmToken::where('last_seen_at', '<', $before)->delete();
    }
}
