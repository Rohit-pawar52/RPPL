<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendNotificationJob;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Scheduler foundation phase — the Admin-facing half of scheduled
 * announcement push notifications (notification_enabled/_scheduled_at/
 * _dispatched_at). Never sends a real Firebase message: SendNotificationJob
 * is faked via Queue::fake() throughout, exactly like NotificationSendTest.
 * See DispatchScheduledAnnouncementsTest for the scheduler-scan/duplicate-
 * protection half of this feature.
 */
class AnnouncementNotificationSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    // ----- Backward compatibility -----

    public function test_existing_announcement_creation_still_works_without_a_notification_choice(): void
    {
        // Mirrors AnnouncementManagementTest's own create payloads, which
        // predate this feature and never send notification_choice.
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Plain announcement',
            'is_active' => '1',
        ])->assertRedirect(route('admin.announcements.index'));

        $announcement = Announcement::where('message', 'Plain announcement')->firstOrFail();
        $this->assertFalse($announcement->notification_enabled);
        $this->assertNull($announcement->notification_scheduled_at);
    }

    // ----- Choice: none / now / later -----

    public function test_admin_can_choose_no_push(): void
    {
        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'No push please',
            'is_active' => '1',
            'notification_choice' => 'none',
        ]);

        $announcement = Announcement::where('message', 'No push please')->firstOrFail();
        $this->assertFalse($announcement->notification_enabled);
        $this->assertSame(0, Notification::count());
    }

    public function test_admin_can_schedule_a_future_push(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Future push',
            'is_active' => '1',
            'notification_choice' => 'later',
            'notification_scheduled_at' => now('Asia/Kolkata')->addDays(2)->format('Y-m-d\TH:i'),
        ]);

        $announcement = Announcement::where('message', 'Future push')->firstOrFail();
        $this->assertTrue($announcement->notification_enabled);
        $this->assertNotNull($announcement->notification_scheduled_at);
        $this->assertNull($announcement->notification_dispatched_at);
        $this->assertSame(0, Notification::count());
    }

    public function test_scheduled_time_is_converted_from_display_timezone_to_utc(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');

        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Timezone check',
            'is_active' => '1',
            'notification_choice' => 'later',
            'notification_scheduled_at' => '2026-09-30T20:00',
        ]);

        $announcement = Announcement::where('message', 'Timezone check')->firstOrFail();

        // 8:00 PM IST (UTC+5:30) is 14:30 UTC the same day.
        $this->assertSame('2026-09-30 14:30:00', $announcement->notification_scheduled_at->format('Y-m-d H:i:s'));
    }

    public function test_past_scheduled_time_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Bad schedule',
            'is_active' => '1',
            'notification_choice' => 'later',
            'notification_scheduled_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]);

        $response->assertSessionHasErrors('notification_scheduled_at');
        $this->assertDatabaseMissing('announcements', ['message' => 'Bad schedule']);
    }

    public function test_send_now_dispatches_immediately_through_the_existing_pipeline(): void
    {
        Queue::fake();

        $this->actingAs($this->admin())->post(route('admin.announcements.store'), [
            'message' => 'Send this now',
            'is_active' => '1',
            'notification_choice' => 'now',
        ]);

        $announcement = Announcement::where('message', 'Send this now')->firstOrFail();

        $this->assertNotNull($announcement->notification_dispatched_at);
        $this->assertNotNull($announcement->notification_id);
        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class);
    }

    // ----- Editing before/after dispatch -----

    public function test_admin_can_change_the_schedule_before_dispatch(): void
    {
        $announcement = Announcement::factory()->create([
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->addDay(),
            'notification_dispatched_at' => null,
        ]);
        $newTime = now()->addDays(5)->format('Y-m-d\TH:i');

        $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), [
            'message' => $announcement->message,
            'is_active' => '1',
            'notification_choice' => 'later',
            'notification_scheduled_at' => $newTime,
        ]);

        $announcement->refresh();
        $this->assertTrue($announcement->notification_enabled);
        $this->assertNotNull($announcement->notification_scheduled_at);
    }

    public function test_admin_can_cancel_a_pending_schedule(): void
    {
        $announcement = Announcement::factory()->create([
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->addDay(),
            'notification_dispatched_at' => null,
        ]);

        $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), [
            'message' => $announcement->message,
            'is_active' => '1',
            'notification_choice' => 'none',
        ]);

        $announcement->refresh();
        $this->assertFalse($announcement->notification_enabled);
    }

    public function test_editing_after_dispatch_does_not_resend_or_change_the_schedule(): void
    {
        Queue::fake();
        $notification = Notification::factory()->create();
        $announcement = Announcement::factory()->create([
            'notification_enabled' => true,
            'notification_scheduled_at' => null,
            'notification_dispatched_at' => now(),
            'notification_id' => $notification->id,
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.announcements.update', $announcement), [
            'message' => 'Edited after send',
            'is_active' => '1',
            'notification_choice' => 'later',
            'notification_scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $announcement->refresh();
        $this->assertSame('Edited after send', $announcement->message);
        // notification_scheduled_at must NOT have been set from the
        // submitted "later" choice — dispatched_at being non-null already
        // locked out any further notification-field changes.
        $this->assertNull($announcement->notification_scheduled_at);
        $this->assertSame(1, Notification::count()); // still just the one from setup, no new one created
        $this->assertSame(0, NotificationSend::count());
    }

    // ----- Public visibility unaffected -----

    public function test_public_ticker_visibility_is_unaffected_by_notification_scheduling_fields(): void
    {
        $visible = Announcement::factory()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'notification_enabled' => true,
            'notification_scheduled_at' => now()->addDay(),
        ]);
        $hidden = Announcement::factory()->create([
            'is_active' => false,
            'notification_enabled' => false,
        ]);

        $response = $this->get(route('public.home'));

        $response->assertSee($visible->message);
        $response->assertDontSee($hidden->message);
    }
}
