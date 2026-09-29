<?php

namespace App\Services\MatchReminder;

use App\Models\GameMatch;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationSendService;
use App\Services\Settings\DisplayTimezoneFormatter;
use Illuminate\Support\Facades\DB;

/**
 * Claims and dispatches ONE match's reminder push through the existing
 * Notification/NotificationSend/SendNotificationJob pipeline — mirrors
 * AnnouncementNotificationService exactly. See DispatchMatchReminders
 * for the scheduler-driven caller.
 *
 * The due instant is always DERIVED as scheduled_at minus
 * reminder_minutes_before, never stored — so rescheduling a match before
 * its reminder fires naturally moves the reminder with it, with no
 * special-case code needed anywhere.
 */
class MatchReminderService
{
    public function __construct(
        private readonly NotificationSendService $notificationSends,
        private readonly DisplayTimezoneFormatter $displayTimezone,
    ) {}

    /**
     * @return bool true if a reminder was actually dispatched
     */
    public function dispatchIfDue(int $matchId): bool
    {
        return DB::transaction(function () use ($matchId) {
            $match = GameMatch::query()
                ->dueForReminder()
                ->whereKey($matchId)
                ->with(['edition', 'teamA.team', 'teamB.team', 'venue'])
                ->lockForUpdate()
                ->first();

            if (! $match || ! $this->isDueNow($match)) {
                return false;
            }

            $admin = User::whereHas('role', fn ($query) => $query->where('slug', 'admin'))->first();

            if (! $admin) {
                // No admin user exists to attribute the send to — an
                // edge case that shouldn't occur in a real deployment.
                // Leave the reminder unclaimed so it's retried once one
                // does, rather than failing loudly from a scheduled task.
                return false;
            }

            $notification = Notification::create([
                'title' => $this->title($match),
                'message' => $this->body($match),
                'action_url' => route('public.matches.show', $match, absolute: false),
                'created_by' => $admin->id,
            ]);

            $result = $this->notificationSends->send($notification, $admin);

            if (! $result['dispatched']) {
                return false;
            }

            $match->update([
                'notification_id' => $notification->id,
                'reminder_dispatched_at' => now(),
            ]);

            return true;
        });
    }

    /**
     * The due-time check GameMatch::scopeDueForReminder() deliberately
     * leaves in PHP (see its docblock) — portable across SQLite/MySQL,
     * and cheap on the narrow candidate set the scope already filtered
     * down to.
     */
    private function isDueNow(GameMatch $match): bool
    {
        return $match->scheduled_at->copy()->subMinutes($match->reminder_minutes_before)->isPast();
    }

    private function title(GameMatch $match): string
    {
        $teamA = $match->teamA->team->short_name ?: $match->teamA->team->name;
        $teamB = $match->teamB->team->short_name ?: $match->teamB->team->name;

        return "{$teamA} vs {$teamB} starts soon";
    }

    private function body(GameMatch $match): string
    {
        $time = $this->displayTimezone->format($match->scheduled_at, 'h:i A');
        $parts = [$match->edition->name, "Match starts at {$time}"];

        if ($match->venue) {
            $parts[] = $match->venue->name;
        }

        return implode(' • ', $parts);
    }
}
