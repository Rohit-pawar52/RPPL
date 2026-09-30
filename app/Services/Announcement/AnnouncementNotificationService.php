<?php

namespace App\Services\Announcement;

use App\Models\Announcement;
use App\Models\Notification;
use App\Services\Notification\NotificationSendService;
use Illuminate\Support\Facades\DB;

/**
 * Claims and dispatches ONE announcement's scheduled push notification
 * through the existing Notification/NotificationSend/SendNotificationJob
 * pipeline — never a second Firebase-calling path. Reused by both the
 * "Send Now" admin action (AnnouncementController, called synchronously
 * right after save) and the scheduler-driven scan
 * (DispatchScheduledAnnouncements), so there is exactly one claim/dispatch
 * implementation rather than two that could quietly drift apart.
 *
 * "Send Now" and "Schedule for Later" are the SAME mechanism underneath:
 * a null notification_scheduled_at simply counts as immediately due (see
 * Announcement::scopeDueForNotification()), so if the synchronous
 * "Send Now" attempt fails for any reason (e.g. the queue connection is
 * briefly unavailable), the very next scheduler pass retries it
 * automatically — no separate retry path to maintain.
 */
class AnnouncementNotificationService
{
    public function __construct(private readonly NotificationSendService $notificationSends) {}

    /**
     * Atomically claims and dispatches $announcementId's notification if
     * it is still due — re-fetches and locks the row fresh (never trusts
     * a possibly-stale in-memory instance), so this is safe to call
     * concurrently from both the admin request path and a scheduler run.
     *
     * notification_dispatched_at is set ONLY when SendNotificationJob was
     * actually confirmed queued (NotificationSendService::send()'s
     * 'dispatched' flag) — never merely because an attempt was made. A
     * dispatch failure leaves the row exactly as it was, so the next
     * scheduler pass retries it; this can never silently mark something
     * "sent" that never reached the queue.
     *
     * @return bool true if a notification was actually dispatched
     */
    public function dispatchIfDue(int $announcementId): bool
    {
        return DB::transaction(function () use ($announcementId) {
            $announcement = Announcement::query()
                ->dueForNotification()
                ->whereKey($announcementId)
                ->lockForUpdate()
                ->first();

            if (! $announcement) {
                return false;
            }

            $notification = Notification::create([
                'title' => 'RPPL Announcement',
                'message' => $announcement->message,
                'action_url' => null,
                'created_by' => $announcement->created_by,
            ]);

            $result = $this->notificationSends->send($notification, $announcement->creator);

            if (! $result['dispatched']) {
                return false;
            }

            $announcement->update([
                'notification_id' => $notification->id,
                'notification_dispatched_at' => now(),
            ]);

            return true;
        });
    }
}
