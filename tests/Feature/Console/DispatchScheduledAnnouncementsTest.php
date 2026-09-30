<?php

namespace Tests\Feature\Console;

use App\Jobs\SendNotificationJob;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Scheduler foundation phase — rppl:dispatch-scheduled-announcements,
 * registered every minute in bootstrap/app.php's ->withSchedule().
 * Proves the due/claim/dispatch scan and, critically, that repeated
 * invocation (exactly what a real every-minute cron produces) can never
 * duplicate-send. Never sends a real Firebase message — SendNotificationJob
 * is faked via Queue::fake() throughout.
 */
class DispatchScheduledAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]);
    }

    public function test_future_scheduled_announcement_is_not_dispatched_early(): void
    {
        Queue::fake();
        Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->addDay(),
        ]);

        Artisan::call('rppl:dispatch-scheduled-announcements');

        $this->assertSame(0, NotificationSend::count());
        Queue::assertNotPushed(SendNotificationJob::class);
    }

    public function test_due_announcement_dispatches_exactly_once(): void
    {
        Queue::fake();
        $announcement = Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->subMinute(),
        ]);

        Artisan::call('rppl:dispatch-scheduled-announcements');

        $announcement->refresh();
        $this->assertNotNull($announcement->notification_dispatched_at);
        $this->assertNotNull($announcement->notification_id);
        $this->assertSame(1, NotificationSend::count());
        $this->assertSame(1, Notification::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_send_now_style_null_scheduled_at_is_treated_as_immediately_due(): void
    {
        Queue::fake();
        Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => true,
            'notification_scheduled_at' => null,
        ]);

        Artisan::call('rppl:dispatch-scheduled-announcements');

        $this->assertSame(1, NotificationSend::count());
    }

    /**
     * The exact scenario an every-minute cron produces in real
     * operation: the same due row is scanned again before its
     * dispatched_at claim ever existed the first time this test ran the
     * command. Running the command twice back-to-back must still result
     * in exactly one send.
     */
    public function test_running_the_command_twice_does_not_duplicate_the_send(): void
    {
        Queue::fake();
        Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->subMinute(),
        ]);

        Artisan::call('rppl:dispatch-scheduled-announcements');
        Artisan::call('rppl:dispatch-scheduled-announcements');

        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class, 1);
    }

    public function test_disabled_announcement_is_never_dispatched(): void
    {
        Queue::fake();
        Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => false,
            'notification_scheduled_at' => now()->subMinute(),
        ]);

        Artisan::call('rppl:dispatch-scheduled-announcements');

        $this->assertSame(0, NotificationSend::count());
    }

    public function test_already_dispatched_announcement_is_never_reprocessed(): void
    {
        Queue::fake();
        $notification = Notification::factory()->create();
        Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->subDay(),
            'notification_dispatched_at' => now()->subHour(),
            'notification_id' => $notification->id,
        ]);

        Artisan::call('rppl:dispatch-scheduled-announcements');

        $this->assertSame(0, NotificationSend::count());
    }

    /**
     * Mirrors NotificationSendTest's own technique for forcing a genuine
     * dispatch-time failure (not a mock): an invalid queue connection.
     * dispatchIfDue() must leave notification_dispatched_at null so the
     * very next scheduler pass retries it, rather than ever marking a
     * failed attempt as if it had succeeded.
     */
    public function test_a_dispatch_failure_leaves_the_announcement_retriable_on_the_next_pass(): void
    {
        $announcement = Announcement::factory()->create([
            'created_by' => $this->admin()->id,
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->subMinute(),
        ]);

        config(['queue.default' => 'nonexistent-connection']);
        Artisan::call('rppl:dispatch-scheduled-announcements');

        $announcement->refresh();
        $this->assertNull($announcement->notification_dispatched_at);

        // A NotificationSend row was created (NotificationSendService's
        // own always-atomic create()) even though the dispatch failed —
        // this is the existing, deliberate "visible incomplete attempt"
        // behavior; what matters is dispatched_at staying null so retry
        // is possible.
        config(['queue.default' => 'sync']);
        Queue::fake();
        Artisan::call('rppl:dispatch-scheduled-announcements');

        $announcement->refresh();
        $this->assertNotNull($announcement->notification_dispatched_at);
    }
}
