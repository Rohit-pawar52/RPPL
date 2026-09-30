<?php

namespace App\Services\TournamentDay;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Notification;
use App\Models\TournamentDayNotification;
use App\Models\User;
use App\Services\Notification\NotificationSendService;
use App\Services\Settings\DisplayTimezoneFormatter;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tournament-Day Morning Reminder — ONE summary push per edition per
 * match day, through the exact same Notification/NotificationSend/
 * SendNotificationJob pipeline as MatchReminderService. See
 * DispatchTournamentDayReminders for the scheduler-driven caller.
 *
 * "Today" is always the calendar date in system.display_timezone, never
 * UTC. Idempotency lives in tournament_day_notifications (UNIQUE
 * edition_id + notification_date); a row is written only once the job
 * was actually queued, so a failed dispatch is retried on the next run.
 */
class TournamentDayReminderService
{
    /**
     * Upcoming/in-progress statuses. 'live' is included so a command that
     * runs late doesn't drop a match that has only just started.
     */
    private const ELIGIBLE_STATUSES = ['scheduled', 'toss', 'live'];

    /**
     * Deterministic "morning" cutoff in display-timezone wall-clock time:
     * a late scheduler run still sends before noon, but a stale "morning"
     * summary is never sent in the afternoon. Consequently a configured
     * send time at or after 12:00 never sends anything.
     */
    private const CUTOFF = '12:00';

    public function __construct(
        private readonly NotificationSendService $notificationSends,
        private readonly DisplayTimezoneFormatter $displayTimezone,
        private readonly SettingsService $settings,
    ) {}

    /**
     * Editions with at least one eligible match today that have not yet
     * had their summary queued — empty outside the send window.
     *
     * @return Collection<int, int>
     */
    public function dueEditionIds(?Carbon $now = null): Collection
    {
        $now ??= now();

        if (! $this->withinSendWindow($now)) {
            return collect();
        }

        $date = $this->localDate($now);

        return $this->eligibleMatchesQuery($now)
            ->whereNotIn('edition_id', TournamentDayNotification::query()
                ->where('notification_date', $date)
                ->select('edition_id'))
            ->distinct()
            ->orderBy('edition_id')
            ->pluck('edition_id');
    }

    /**
     * @return bool true if a summary was actually dispatched
     */
    public function dispatchIfDue(int $editionId, ?Carbon $now = null): bool
    {
        $now ??= now();

        if (! $this->withinSendWindow($now)) {
            return false;
        }

        $date = $this->localDate($now);

        return DB::transaction(function () use ($editionId, $now, $date) {
            // Locking the edition row serializes concurrent claims for
            // the same edition/day; the UNIQUE index is the final backstop.
            $edition = Edition::whereKey($editionId)->lockForUpdate()->first();

            if (! $edition) {
                return false;
            }

            $alreadySent = TournamentDayNotification::query()
                ->where('edition_id', $editionId)
                ->where('notification_date', $date)
                ->exists();

            if ($alreadySent) {
                return false;
            }

            $matches = $this->eligibleMatchesQuery($now)
                ->where('edition_id', $editionId)
                ->with(['teamA.team', 'teamB.team', 'venue'])
                ->orderBy('scheduled_at')
                ->orderBy('id')
                ->get();

            if ($matches->isEmpty()) {
                return false;
            }

            $admin = User::whereHas('role', fn ($query) => $query->where('slug', 'admin'))->first();

            if (! $admin) {
                // Same as MatchReminderService: leave it unclaimed so it
                // is retried once an admin exists.
                return false;
            }

            $notification = Notification::create([
                'title' => $matches->count() === 1 ? "Today's RPPL Match" : "Today's RPPL Matches",
                'message' => $this->body($matches),
                'action_url' => route('public.matches.index', absolute: false),
                'created_by' => $admin->id,
            ]);

            $result = $this->notificationSends->send($notification, $admin);

            if (! $result['dispatched']) {
                return false;
            }

            TournamentDayNotification::create([
                'edition_id' => $editionId,
                'notification_date' => $date,
                'notification_id' => $notification->id,
                'dispatched_at' => now(),
            ]);

            return true;
        });
    }

    private function withinSendWindow(Carbon $now): bool
    {
        if (! $this->settings->boolean('notifications.tournament_day_reminder_enabled')) {
            return false;
        }

        $sendAt = (string) $this->settings->get('notifications.tournament_day_reminder_time');

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $sendAt)) {
            return false;
        }

        // Zero-padded "H:i" strings compare correctly lexicographically.
        $localTime = $this->local($now)->format('H:i');

        return $localTime >= $sendAt && $localTime < self::CUTOFF;
    }

    /**
     * Eligible matches whose scheduled_at falls within today's
     * display-timezone calendar day, expressed as a UTC half-open range.
     */
    private function eligibleMatchesQuery(Carbon $now): Builder
    {
        $start = $this->local($now)->startOfDay();
        $end = $start->copy()->addDay();

        return GameMatch::query()
            ->whereIn('match_status', self::ELIGIBLE_STATUSES)
            ->where('scheduled_at', '>=', $start->copy()->setTimezone('UTC'))
            ->where('scheduled_at', '<', $end->setTimezone('UTC'));
    }

    private function local(Carbon $now): Carbon
    {
        return $now->copy()->setTimezone($this->settings->get('system.display_timezone'));
    }

    private function localDate(Carbon $now): string
    {
        return $this->local($now)->toDateString();
    }

    /**
     * @param  Collection<int, GameMatch>  $matches  ordered earliest first
     */
    private function body(Collection $matches): string
    {
        $first = $matches->first();
        $fixture = sprintf(
            '%s vs %s at %s',
            $this->teamLabel($first->teamA),
            $this->teamLabel($first->teamB),
            $this->displayTimezone->format($first->scheduled_at, 'h:i A'),
        );

        if ($matches->count() > 1) {
            return "{$matches->count()} matches today. First: {$fixture}.";
        }

        return $first->venue ? "{$fixture} — {$first->venue->name}" : $fixture;
    }

    private function teamLabel(EditionTeam $editionTeam): string
    {
        return $editionTeam->team->short_name ?: $editionTeam->team->name;
    }
}
