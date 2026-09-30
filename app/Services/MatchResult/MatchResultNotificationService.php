<?php

namespace App\Services\MatchResult;

use App\Models\GameMatch;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationSendService;
use Illuminate\Support\Facades\DB;

/**
 * Claims and dispatches ONE match's result push through the existing
 * Notification/NotificationSend/SendNotificationJob pipeline — mirrors
 * AnnouncementNotificationService/MatchReminderService exactly. Unlike
 * those two, this is never scheduler-driven: it's called synchronously
 * right after MatchFlowController::finalize()/recordSuperOverResult()
 * succeed (a real admin is always present in that request), and again,
 * identically, by the small manual "Send Result Notification" recovery
 * action for the rare case where the automatic attempt failed to queue.
 */
class MatchResultNotificationService
{
    public function __construct(private readonly NotificationSendService $notificationSends) {}

    /**
     * Atomically claims and dispatches $matchId's result notification if
     * it is still due — re-fetches and locks the row fresh (never trusts
     * a possibly-stale in-memory instance), so this is safe to call
     * concurrently from a double-submitted finalize request, a page
     * refresh, or the manual recovery action.
     *
     * result_notification_dispatched_at is set ONLY when
     * SendNotificationJob was actually confirmed queued
     * (NotificationSendService::send()'s 'dispatched' flag) — never
     * merely because an attempt was made. A dispatch failure leaves the
     * row exactly as it was, so it stays eligible for the manual
     * recovery action (or a future finalize-adjacent retry); this can
     * never silently mark something "sent" that never reached the queue.
     *
     * @return bool true if a notification was actually dispatched
     */
    public function dispatchIfDue(int $matchId, User $admin): bool
    {
        return DB::transaction(function () use ($matchId, $admin) {
            $match = GameMatch::query()
                ->dueForResultNotification()
                ->whereKey($matchId)
                ->lockForUpdate()
                ->first();

            if (! $match) {
                return false;
            }

            $notification = Notification::create([
                'title' => 'RPPL Match Result',
                'message' => $match->match_result,
                'action_url' => route('public.matches.show', $match, absolute: false),
                'created_by' => $admin->id,
            ]);

            $result = $this->notificationSends->send($notification, $admin);

            if (! $result['dispatched']) {
                return false;
            }

            $match->update([
                'result_notification_id' => $notification->id,
                'result_notification_dispatched_at' => now(),
            ]);

            return true;
        });
    }
}
