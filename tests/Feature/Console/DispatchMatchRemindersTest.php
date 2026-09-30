<?php

namespace Tests\Feature\Console;

use App\Jobs\SendNotificationJob;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\Settings\DisplayTimezoneFormatter;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Scheduler foundation phase — rppl:dispatch-match-reminders, registered
 * every minute in bootstrap/app.php's ->withSchedule(). Proves the due/
 * claim/dispatch scan, duplicate protection under repeated invocation,
 * reschedule/cancellation behavior, and the scheduler-downtime eligibility
 * window. Never sends a real Firebase message — SendNotificationJob is
 * faked via Queue::fake() throughout.
 */
class DispatchMatchRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin']);
        app(SettingsService::class)->flush();
    }

    /**
     * @return array{0: GameMatch, 1: Team, 2: Team}
     */
    private function matchWithReminder(array $overrides = []): array
    {
        User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);

        $edition = Edition::factory()->create(['name' => 'RPPL 2026']);
        $teamA = Team::factory()->create(['name' => 'Mumbai Indians', 'short_name' => 'MI']);
        $teamB = Team::factory()->create(['name' => 'Royal Challengers Bengaluru', 'short_name' => 'RCB']);
        $editionTeamA = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $teamA->id]);
        $editionTeamB = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $teamB->id]);
        $venue = Venue::factory()->create(['name' => 'RPPL Ground']);

        $match = GameMatch::factory()->create(array_merge([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $editionTeamA->id,
            'edition_team_b_id' => $editionTeamB->id,
            'venue_id' => $venue->id,
            'match_status' => 'scheduled',
            'reminder_enabled' => true,
            'reminder_minutes_before' => 30,
            'scheduled_at' => now()->addMinutes(20), // 10 minutes overdue
        ], $overrides));

        return [$match, $teamA, $teamB];
    }

    public function test_not_yet_due_match_is_ignored(): void
    {
        Queue::fake();
        [$match] = $this->matchWithReminder(['scheduled_at' => now()->addHours(2)]);

        Artisan::call('rppl:dispatch-match-reminders');

        $match->refresh();
        $this->assertNull($match->reminder_dispatched_at);
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_due_upcoming_match_dispatches_a_reminder(): void
    {
        Queue::fake();
        [$match] = $this->matchWithReminder();

        Artisan::call('rppl:dispatch-match-reminders');

        $match->refresh();
        $this->assertNotNull($match->reminder_dispatched_at);
        $this->assertNotNull($match->notification_id);
        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_running_the_command_twice_does_not_duplicate_the_reminder(): void
    {
        Queue::fake();
        $this->matchWithReminder();

        Artisan::call('rppl:dispatch-match-reminders');
        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(1, NotificationSend::count());
    }

    public function test_reminder_disabled_match_is_ignored(): void
    {
        Queue::fake();
        $this->matchWithReminder(['reminder_enabled' => false]);

        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(0, NotificationSend::count());
    }

    public function test_cancelled_match_is_ignored(): void
    {
        Queue::fake();
        $this->matchWithReminder(['match_status' => 'cancelled']);

        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(0, NotificationSend::count());
    }

    public function test_abandoned_match_is_ignored(): void
    {
        Queue::fake();
        $this->matchWithReminder(['match_status' => 'abandoned']);

        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(0, NotificationSend::count());
    }

    public function test_completed_match_is_ignored(): void
    {
        Queue::fake();
        $this->matchWithReminder(['match_status' => 'completed']);

        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(0, NotificationSend::count());
    }

    public function test_live_match_is_ignored(): void
    {
        Queue::fake();
        // A live match is, by definition, already underway — a "starts
        // soon" reminder would be stale even if it were somehow still
        // within the enabled/not-yet-dispatched window.
        $this->matchWithReminder(['match_status' => 'live']);

        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(0, NotificationSend::count());
    }

    /**
     * The due instant is DERIVED (scheduled_at - reminder_minutes_before),
     * never stored — so rescheduling before dispatch naturally moves it.
     */
    public function test_rescheduling_before_dispatch_follows_the_new_scheduled_at(): void
    {
        Queue::fake();
        [$match] = $this->matchWithReminder(['scheduled_at' => now()->addHours(3)]); // not due yet

        Artisan::call('rppl:dispatch-match-reminders');
        $this->assertSame(0, NotificationSend::count());

        $match->update(['scheduled_at' => now()->addMinutes(10)]); // now overdue (30 min reminder)
        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(1, NotificationSend::count());
    }

    public function test_already_sent_reminder_is_not_resent_after_rescheduling(): void
    {
        Queue::fake();
        [$match] = $this->matchWithReminder();

        Artisan::call('rppl:dispatch-match-reminders');
        $this->assertSame(1, NotificationSend::count());

        // Admin reschedules the match to a new future time after the
        // reminder already fired — this must never trigger a second send.
        $match->update(['scheduled_at' => now()->addHours(5)]);
        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertSame(1, NotificationSend::count());
    }

    /**
     * Scheduler downtime scenario: the due instant (scheduled_at minus
     * reminder_minutes_before) is well in the past, but the match itself
     * hasn't started yet — the reminder is still useful and must send.
     */
    public function test_a_reminder_overdue_due_to_scheduler_downtime_still_sends_before_match_start(): void
    {
        Queue::fake();
        // Due 25 minutes ago (30-minute reminder, match in 5 minutes).
        [$match] = $this->matchWithReminder(['scheduled_at' => now()->addMinutes(5)]);

        Artisan::call('rppl:dispatch-match-reminders');

        $match->refresh();
        $this->assertNotNull($match->reminder_dispatched_at);
    }

    /**
     * Once the match's own scheduled_at has passed, a reminder is stale
     * regardless of how enabled/undispatched it still looks — this is
     * the "do not send hours after the match" guard.
     */
    public function test_a_reminder_is_not_sent_once_the_match_start_time_has_passed(): void
    {
        Queue::fake();
        [$match] = $this->matchWithReminder(['scheduled_at' => now()->subMinutes(5)]);

        Artisan::call('rppl:dispatch-match-reminders');

        $match->refresh();
        $this->assertNull($match->reminder_dispatched_at);
        $this->assertSame(0, NotificationSend::count());
    }

    public function test_reminder_content_uses_team_names_edition_and_display_timezone(): void
    {
        Queue::fake();
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');
        [$match] = $this->matchWithReminder(); // scheduled_at = now()+20min, 30-min reminder = due

        Artisan::call('rppl:dispatch-match-reminders');

        $notification = Notification::firstOrFail();
        $expectedTime = app(DisplayTimezoneFormatter::class)
            ->format($match->fresh()->scheduled_at, 'h:i A');

        $this->assertStringContainsString('MI', $notification->title);
        $this->assertStringContainsString('RCB', $notification->title);
        $this->assertStringContainsString('RPPL 2026', $notification->message);
        $this->assertStringContainsString($expectedTime, $notification->message);
    }
}
