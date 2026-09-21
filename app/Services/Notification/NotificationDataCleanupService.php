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
 */
class NotificationDataCleanupService
{
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

    /**
     * Deletes NotificationSend history rows older than $before,
     * independently of their parent Notification (which is left
     * untouched either way — only this phase's date-based Notification
     * cleanup above ever removes a Notification itself).
     *
     * @return int number of NotificationSend rows deleted
     */
    public function deleteNotificationSendsBefore(Carbon $before): int
    {
        return NotificationSend::where('created_at', '<', $before)->delete();
    }

    /**
     * Keeps only the $keepCount most recently active FcmToken rows
     * (ordered by last_seen_at, newest first — ties broken by id so the
     * result is deterministic) and deletes everything beyond that. This
     * naturally tends to remove long-stale/abandoned tokens first, since
     * those already have the oldest last_seen_at values — but if
     * $keepCount is set lower than the number of genuinely active
     * subscribers, some currently-valid tokens ARE deleted; the admin
     * UI makes that consequence explicit before this runs.
     *
     * @return int number of FcmToken rows deleted
     */
    public function keepLatestFcmTokens(int $keepCount): int
    {
        $idsToKeep = FcmToken::query()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->limit($keepCount)
            ->pluck('id');

        return FcmToken::query()->whereNotIn('id', $idsToKeep)->delete();
    }
}
