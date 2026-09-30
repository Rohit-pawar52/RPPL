<?php

namespace Tests\Feature\Console;

use App\Jobs\SendNotificationJob;
use App\Models\Edition;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use App\Services\Registration\RegistrationClosingReminderService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * rppl:dispatch-registration-closing-reminders — due instant derived as
 * registration_closes_at minus the reminder offset. SendNotificationJob is
 * always faked; no real Firebase call is ever made.
 */
class DispatchRegistrationClosingRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        User::factory()->create(['role_id' => $role->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function editionWithReminder(array $overrides = []): Edition
    {
        return Edition::factory()->create(array_merge([
            'name' => 'RPPL 2026',
            'status' => 'active',
            'registration_open' => true,
            'registration_fee' => 400.00,
            'registration_closes_at' => now()->addHours(10), // 24h reminder overdue by 14h
            'registration_reminder_enabled' => true,
            'registration_reminder_minutes_before' => 1440,
        ], $overrides));
    }

    private function runCommand(): void
    {
        Artisan::call('rppl:dispatch-registration-closing-reminders');
    }

    public function test_disabled_reminder_queues_nothing(): void
    {
        Queue::fake();
        $edition = $this->editionWithReminder(['registration_reminder_enabled' => false]);

        $this->runCommand();

        $this->assertNull($edition->fresh()->registration_reminder_dispatched_at);
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_edition_without_closing_time_queues_nothing(): void
    {
        Queue::fake();
        $this->editionWithReminder(['registration_closes_at' => null]);

        $this->runCommand();

        $this->assertSame(0, Notification::count());
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_before_due_time_nothing_is_queued(): void
    {
        Queue::fake();
        $edition = $this->editionWithReminder(['registration_closes_at' => now()->addHours(30)]);

        $this->runCommand();

        $this->assertNull($edition->fresh()->registration_reminder_dispatched_at);
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_due_reminder_queues_exactly_one_notification_with_correct_content(): void
    {
        Queue::fake();
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');
        // 20:30 IST on 1 Oct; registration closes 20:00 IST on 2 Oct.
        Carbon::setTestNow(Carbon::parse('2026-10-01 15:00:00', 'UTC'));
        $edition = $this->editionWithReminder([
            'registration_closes_at' => Carbon::parse('2026-10-02 14:30:00', 'UTC'),
        ]);

        $this->runCommand();
        $this->runCommand();

        $edition->refresh();
        $this->assertNotNull($edition->registration_reminder_dispatched_at);
        $this->assertSame(1, Notification::count());
        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class, 1);

        $notification = Notification::first();
        $this->assertSame($notification->id, $edition->registration_reminder_notification_id);
        $this->assertSame('RPPL Registration Closing Soon', $notification->title);
        $this->assertSame('Player registration for RPPL 2026 closes tomorrow at 8:00 PM.', $notification->message);
        $this->assertSame(route('public.player-registration.create', absolute: false), $notification->action_url);
    }

    public function test_stale_reminder_is_not_sent_after_registration_has_closed(): void
    {
        Queue::fake();
        $edition = $this->editionWithReminder(['registration_closes_at' => now()->subMinute()]);

        $this->runCommand();
        $this->assertFalse(app(RegistrationClosingReminderService::class)->dispatchIfDue($edition->id));

        $this->assertNull($edition->fresh()->registration_reminder_dispatched_at);
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_registration_switched_off_is_not_reminded(): void
    {
        Queue::fake();
        $this->editionWithReminder(['registration_open' => false]);

        $this->runCommand();

        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_repeated_direct_claims_remain_idempotent(): void
    {
        Queue::fake();
        $edition = $this->editionWithReminder();
        $service = app(RegistrationClosingReminderService::class);

        $this->assertTrue($service->dispatchIfDue($edition->id));
        $this->assertFalse($service->dispatchIfDue($edition->id));
        $this->runCommand();

        $this->assertSame(1, Notification::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_queue_dispatch_failure_does_not_mark_reminder_dispatched_and_recovers(): void
    {
        $edition = $this->editionWithReminder();

        config(['queue.default' => 'nonexistent-connection']);
        $this->runCommand();

        $this->assertNull($edition->fresh()->registration_reminder_dispatched_at);

        config(['queue.default' => 'sync']);
        Queue::fake();
        $this->runCommand();

        $this->assertNotNull($edition->fresh()->registration_reminder_dispatched_at);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_moving_the_closing_time_before_dispatch_moves_the_due_time(): void
    {
        Queue::fake();
        $edition = $this->editionWithReminder(['registration_closes_at' => now()->addHours(30)]);

        $this->runCommand();
        Queue::assertNotPushed(SendNotificationJob::class);

        $edition->update(['registration_closes_at' => now()->addHours(12)]);
        $this->runCommand();

        $this->assertNotNull($edition->fresh()->registration_reminder_dispatched_at);
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_already_sent_reminder_is_not_resent_after_deadline_change(): void
    {
        Queue::fake();
        $edition = $this->editionWithReminder();

        $this->runCommand();
        $edition->update(['registration_closes_at' => now()->addHours(5)]);
        $this->runCommand();

        $this->assertSame(1, Notification::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }
}
