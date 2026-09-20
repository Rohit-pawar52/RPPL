<?php

namespace App\Services\Notification;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\User;
use Throwable;

/**
 * Creates the immutable NotificationSend snapshot for a Send/Resend
 * click and queues the actual Firebase send. A single NotificationSend::
 * create() call is already atomic, so — unlike PlayerRegistrationService::
 * createRegistration()'s two-step placeholder-then-real-value write —
 * no explicit DB transaction is needed here.
 *
 * Dispatch happens strictly AFTER that row exists, in a separate
 * try/catch — the same after-commit pattern GuestPlayerRegistrationService
 * already established: a queue-connection failure must never delete or
 * roll back the just-created send-history row, only be reported.
 */
class NotificationSendService
{
    /**
     * @return array{send: NotificationSend, dispatched: bool} dispatched
     *                                                         is false only when SendNotificationJob::dispatch() itself threw
     *                                                         (e.g. the queue connection is unavailable) — the row is created
     *                                                         either way, and the caller uses this to give the admin an
     *                                                         accurate message rather than implying the send is in progress.
     */
    public function send(Notification $notification, User $admin): array
    {
        $send = NotificationSend::create([
            'notification_id' => $notification->id,
            'title_snapshot' => $notification->title,
            'message_snapshot' => $notification->message,
            'action_url_snapshot' => $notification->action_url,
            'attempted_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'sent_by' => $admin->id,
            'completed_at' => null,
        ]);

        $dispatched = true;

        try {
            SendNotificationJob::dispatch($send);
        } catch (Throwable $e) {
            report($e);

            $dispatched = false;
        }

        return ['send' => $send, 'dispatched' => $dispatched];
    }
}
