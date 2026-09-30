<?php

namespace App\Services\Registration;

use App\Models\Edition;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationSendService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Claims and dispatches ONE edition's "registration closing soon" push
 * through the existing Notification/NotificationSend pipeline — same
 * claim-then-dispatch shape as MatchReminderService. The due instant is
 * always derived as registration_closes_at minus the configured minutes,
 * so moving the deadline before dispatch moves the reminder with it.
 * Once dispatched it is never re-sent, even if the deadline later moves.
 */
class RegistrationClosingReminderService
{
    public function __construct(
        private readonly NotificationSendService $notificationSends,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return bool true if a reminder was actually dispatched
     */
    public function dispatchIfDue(int $editionId): bool
    {
        return DB::transaction(function () use ($editionId) {
            $edition = Edition::query()
                ->dueForRegistrationReminder()
                ->whereKey($editionId)
                ->lockForUpdate()
                ->first();

            // The scope already guarantees registration is still open
            // (closes_at in the future), so a scheduler that comes back
            // after the deadline never sends a stale "closing soon".
            if (! $edition || ! $this->isDueNow($edition)) {
                return false;
            }

            $admin = User::whereHas('role', fn ($query) => $query->where('slug', 'admin'))->first();

            if (! $admin) {
                return false;
            }

            $notification = Notification::create([
                'title' => 'RPPL Registration Closing Soon',
                'message' => $this->body($edition),
                'action_url' => route('public.player-registration.create', absolute: false),
                'created_by' => $admin->id,
            ]);

            $result = $this->notificationSends->send($notification, $admin);

            if (! $result['dispatched']) {
                return false;
            }

            $edition->update([
                'registration_reminder_notification_id' => $notification->id,
                'registration_reminder_dispatched_at' => now(),
            ]);

            return true;
        });
    }

    private function isDueNow(Edition $edition): bool
    {
        return ! $edition->registration_closes_at->copy()
            ->subMinutes($edition->registration_reminder_minutes_before)
            ->isFuture();
    }

    private function body(Edition $edition): string
    {
        $timezone = $this->settings->get('system.display_timezone');
        $closesAt = $edition->registration_closes_at->copy()->setTimezone($timezone);
        $today = Carbon::now($timezone)->startOfDay();

        $when = match (true) {
            $closesAt->isSameDay($today) => 'today at '.$closesAt->format('g:i A'),
            $closesAt->isSameDay($today->copy()->addDay()) => 'tomorrow at '.$closesAt->format('g:i A'),
            default => 'on '.$closesAt->format('j M').' at '.$closesAt->format('g:i A'),
        };

        return "Player registration for {$edition->name} closes {$when}.";
    }
}
