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
use App\Models\TournamentDayNotification;
use App\Models\User;
use App\Models\Venue;
use App\Services\Settings\SettingsService;
use App\Services\TournamentDay\TournamentDayReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tournament-Day Morning Reminder — rppl:dispatch-tournament-day-reminders.
 * One summary push per edition per display-timezone calendar day, inside
 * the [configured time, 12:00) local send window. Never sends a real
 * Firebase message — SendNotificationJob is faked via Queue::fake().
 *
 * All clock times below are Asia/Kolkata (UTC+05:30, the default
 * display timezone); the test clock is set in UTC.
 */
class DispatchTournamentDayRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        app(SettingsService::class)->flush();
        User::factory()->create(['role_id' => $this->adminRole->id]);

        app(SettingsService::class)->setMany([
            'system.display_timezone' => 'Asia/Kolkata',
            'notifications.tournament_day_reminder_enabled' => true,
            'notifications.tournament_day_reminder_time' => '07:00',
        ]);

        $this->travelToIst('2026-10-05 07:00');
    }

    private function travelToIst(string $localDateTime): void
    {
        $this->travelTo(Carbon::parse($localDateTime, 'Asia/Kolkata')->setTimezone('UTC'));
    }

    private function edition(string $name = 'RPPL 2026'): Edition
    {
        return Edition::factory()->create(['name' => $name]);
    }

    private function match(Edition $edition, string $localDateTime, array $overrides = [], array $teams = ['MI', 'RCB']): GameMatch
    {
        [$a, $b] = array_map(
            fn (string $short) => EditionTeam::factory()->create([
                'edition_id' => $edition->id,
                'team_id' => Team::factory()->create(['short_name' => $short])->id,
            ]),
            $teams,
        );

        return GameMatch::factory()->create(array_merge([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $a->id,
            'edition_team_b_id' => $b->id,
            'venue_id' => Venue::factory()->create(['name' => 'RPPL Ground'])->id,
            'match_status' => 'scheduled',
            'scheduled_at' => Carbon::parse($localDateTime, 'Asia/Kolkata')->setTimezone('UTC'),
        ], $overrides));
    }

    private function runCommand(): void
    {
        Artisan::call('rppl:dispatch-tournament-day-reminders');
    }

    public function test_no_matches_today_sends_nothing(): void
    {
        Queue::fake();
        $this->match($this->edition(), '2026-10-06 19:00'); // tomorrow

        $this->runCommand();

        $this->assertSame(0, Notification::count());
        $this->assertSame(0, TournamentDayNotification::count());
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_one_match_sends_one_summary_with_fixture_time_and_venue(): void
    {
        Queue::fake();
        $edition = $this->edition();
        $this->match($edition, '2026-10-05 19:00');

        $this->runCommand();

        $notification = Notification::sole();
        $this->assertSame("Today's RPPL Match", $notification->title);
        $this->assertSame('MI vs RCB at 07:00 PM — RPPL Ground', $notification->message);
        $this->assertSame(route('public.matches.index', absolute: false), $notification->action_url);
        $this->assertTrue(Notification::isValidActionUrl($notification->action_url));

        $log = TournamentDayNotification::sole();
        $this->assertSame($edition->id, $log->edition_id);
        $this->assertSame('2026-10-05', $log->notification_date);
        $this->assertSame($notification->id, $log->notification_id);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_multiple_matches_send_exactly_one_summary_naming_the_earliest(): void
    {
        Queue::fake();
        $edition = $this->edition();
        $this->match($edition, '2026-10-05 19:00', teams: ['MI', 'RCB']);
        $this->match($edition, '2026-10-05 15:30', teams: ['CSK', 'KKR']);

        $this->runCommand();

        $notification = Notification::sole();
        $this->assertSame("Today's RPPL Matches", $notification->title);
        $this->assertSame('2 matches today. First: CSK vs KKR at 03:30 PM.', $notification->message);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_completed_cancelled_and_abandoned_matches_are_excluded(): void
    {
        Queue::fake();
        $edition = $this->edition();
        foreach (['completed', 'cancelled', 'abandoned'] as $status) {
            $this->match($edition, '2026-10-05 19:00', ['match_status' => $status]);
        }

        $this->runCommand();

        $this->assertSame(0, Notification::count());
        $this->assertSame(0, TournamentDayNotification::count());
    }

    public function test_live_match_still_counts(): void
    {
        Queue::fake();
        $this->match($this->edition(), '2026-10-05 06:30', ['match_status' => 'live']);

        $this->runCommand();

        $this->assertSame(1, TournamentDayNotification::count());
    }

    public function test_today_is_the_display_timezone_calendar_day_not_utc(): void
    {
        Queue::fake();
        $edition = $this->edition();
        // 00:30 IST on Oct 6 = 19:00 UTC on Oct 5 — same UTC date as
        // "now" but tomorrow locally, so it must NOT count.
        $this->match($edition, '2026-10-06 00:30', teams: ['CSK', 'KKR']);
        // 23:30 IST on Oct 5 = 18:00 UTC on Oct 5 — today locally.
        $this->match($edition, '2026-10-05 23:30', teams: ['MI', 'RCB']);

        $this->runCommand();

        $this->assertSame('MI vs RCB at 11:30 PM — RPPL Ground', Notification::sole()->message);
    }

    public function test_disabled_setting_sends_nothing(): void
    {
        Queue::fake();
        app(SettingsService::class)->set('notifications.tournament_day_reminder_enabled', false);
        $this->match($this->edition(), '2026-10-05 19:00');

        $this->runCommand();

        $this->assertSame(0, Notification::count());
    }

    public function test_send_window_respects_configured_time_late_runs_and_noon_cutoff(): void
    {
        Queue::fake();
        $this->match($this->edition(), '2026-10-05 19:00');

        $this->travelToIst('2026-10-05 06:59');
        $this->runCommand();
        $this->assertSame(0, Notification::count(), 'before configured time');

        $this->travelToIst('2026-10-05 07:25');
        $this->runCommand();
        $this->runCommand();
        $this->assertSame(1, TournamentDayNotification::count(), '25 minutes late still sends, once');
    }

    public function test_sends_exactly_at_configured_time(): void
    {
        Queue::fake();
        $this->match($this->edition(), '2026-10-05 19:00');

        $this->runCommand(); // setUp clock is exactly 07:00 IST

        $this->assertSame(1, TournamentDayNotification::count());
    }

    public function test_nothing_sends_at_or_after_the_noon_cutoff(): void
    {
        Queue::fake();
        $this->match($this->edition(), '2026-10-05 19:00');

        $this->travelToIst('2026-10-05 12:00');
        $this->runCommand();

        $this->assertSame(0, Notification::count());
    }

    public function test_direct_double_dispatch_if_due_sends_once(): void
    {
        Queue::fake();
        $edition = $this->edition();
        $this->match($edition, '2026-10-05 19:00');
        $service = app(TournamentDayReminderService::class);

        $this->assertTrue($service->dispatchIfDue($edition->id));
        $this->assertFalse($service->dispatchIfDue($edition->id));

        $this->assertSame(1, NotificationSend::count());
        $this->assertSame(1, TournamentDayNotification::count());
    }

    public function test_next_match_day_sends_again(): void
    {
        Queue::fake();
        $edition = $this->edition();
        $this->match($edition, '2026-10-05 19:00');
        $this->match($edition, '2026-10-06 19:00', teams: ['CSK', 'KKR']);

        $this->runCommand();
        $this->travelToIst('2026-10-06 07:05');
        $this->runCommand();

        $this->assertSame(
            ['2026-10-05', '2026-10-06'],
            TournamentDayNotification::orderBy('notification_date')->pluck('notification_date')->all(),
        );
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    public function test_two_editions_on_the_same_day_get_separate_summaries(): void
    {
        Queue::fake();
        $first = $this->edition('RPPL 2026');
        $second = $this->edition('RPPL Women 2026');
        $this->match($first, '2026-10-05 19:00');
        $this->match($second, '2026-10-05 10:00', teams: ['CSK', 'KKR']);
        $this->match($second, '2026-10-05 14:00', teams: ['DC', 'SRH']);

        $this->runCommand();

        $this->assertSame(2, Notification::count());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            TournamentDayNotification::pluck('edition_id')->all(),
        );
        $this->assertSame(
            '2 matches today. First: CSK vs KKR at 10:00 AM.',
            TournamentDayNotification::where('edition_id', $second->id)->sole()->notification->message,
        );
    }

    public function test_a_failed_queue_dispatch_leaves_no_log_row_and_a_later_run_succeeds(): void
    {
        $this->match($this->edition(), '2026-10-05 19:00');

        config(['queue.default' => 'nonexistent-connection']);
        $this->runCommand();

        $this->assertSame(0, TournamentDayNotification::count());

        config(['queue.default' => 'sync']);
        Queue::fake();
        $this->travelToIst('2026-10-05 07:01');
        $this->runCommand();

        $this->assertSame(1, TournamentDayNotification::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_match_reminder_still_works_alongside(): void
    {
        Queue::fake();
        // Due for its own 30-minute pre-match reminder right now.
        $match = $this->match($this->edition(), '2026-10-05 07:20', [
            'reminder_enabled' => true,
            'reminder_minutes_before' => 30,
        ]);

        $this->runCommand();
        Artisan::call('rppl:dispatch-match-reminders');

        $this->assertNotNull($match->fresh()->reminder_dispatched_at);
        $this->assertSame(1, TournamentDayNotification::count());
        Queue::assertPushed(SendNotificationJob::class, 2);
    }

    // ----- Admin Settings → System tab -----

    private function systemPayload(array $extra = []): array
    {
        return array_merge([
            'maintenance_mode' => '0',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Asia/Kolkata',
            'committee_minimum_contribution' => '1000',
        ], $extra);
    }

    public function test_admin_can_save_tournament_day_reminder_settings(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->put(route('admin.settings.system.update'), $this->systemPayload([
                'tournament_day_reminder_enabled' => '0',
                'tournament_day_reminder_time' => '06:30',
            ]))
            ->assertRedirect(route('admin.settings.index', ['tab' => 'system']));

        $settings = app(SettingsService::class);
        $this->assertFalse($settings->boolean('notifications.tournament_day_reminder_enabled'));
        $this->assertSame('06:30', $settings->get('notifications.tournament_day_reminder_time'));

        $this->withoutVite()
            ->actingAs($admin)
            ->get(route('admin.settings.index', ['tab' => 'system']))
            ->assertSee('Tournament Day Morning Reminder')
            ->assertSee('value="06:30"', false);
    }

    public function test_saving_system_settings_without_the_new_fields_keeps_stored_values(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->put(route('admin.settings.system.update'), $this->systemPayload())
            ->assertSessionHasNoErrors();

        $settings = app(SettingsService::class);
        $this->assertTrue($settings->boolean('notifications.tournament_day_reminder_enabled'));
        $this->assertSame('07:00', $settings->get('notifications.tournament_day_reminder_time'));
    }

    public function test_invalid_reminder_time_is_rejected(): void
    {
        $admin = User::factory()->create(['role_id' => $this->adminRole->id]);

        $this->actingAs($admin)
            ->put(route('admin.settings.system.update'), $this->systemPayload(['tournament_day_reminder_time' => '7am']))
            ->assertSessionHasErrors('tournament_day_reminder_time');
    }
}
