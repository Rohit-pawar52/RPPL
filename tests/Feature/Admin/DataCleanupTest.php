<?php

namespace Tests\Feature\Admin;

use App\Models\DataCleanupLog;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B5 / Phase 3.49 — bulk, date-based retention cleanup for
 * notifications and notification_sends, tab dispatch, and
 * authorization. FCM token cleanup is covered by
 * DataCleanupFcmTokenTest, registration documents by
 * DataCleanupRegistrationDocumentsTest, and failed jobs by
 * DataCleanupFailedJobsTest — all four share this same admin-only gate
 * and DataCleanupLogger audit trail.
 */
class DataCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    // ----- Authorization -----

    public function test_admin_can_access_data_cleanup(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.data-cleanup.index'))->assertOk();
    }

    public function test_scorer_cannot_access_data_cleanup(): void
    {
        $scorer = $this->scorer();

        $this->actingAs($scorer)->get(route('admin.data-cleanup.index'))->assertForbidden();
    }

    public function test_guest_cannot_access_data_cleanup(): void
    {
        $this->get(route('admin.data-cleanup.index'))->assertRedirect(route('admin.login'));
    }

    public function test_preview_endpoint_is_forbidden_for_scorer(): void
    {
        $this->actingAs($this->scorer())
            ->getJson(route('admin.data-cleanup.preview.cutoff', ['category' => 'notifications', 'before_date' => now()->toDateString()]))
            ->assertForbidden();
    }

    public function test_preview_endpoint_redirects_guest_to_login(): void
    {
        $this->get(route('admin.data-cleanup.preview.cutoff', ['category' => 'notifications', 'before_date' => now()->toDateString()]))
            ->assertRedirect(route('admin.login'));
    }

    // ----- Tab dispatch -----

    public function test_invalid_tab_falls_back_to_notifications(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.data-cleanup.index', ['tab' => 'not-a-real-tab']));

        $response->assertOk();
        $response->assertViewHas('activeTab', 'notifications');
    }

    public function test_each_real_tab_renders(): void
    {
        foreach (['notifications', 'registration-documents', 'system'] as $tab) {
            $this->actingAs($this->admin())
                ->get(route('admin.data-cleanup.index', ['tab' => $tab]))
                ->assertOk()
                ->assertViewHas('activeTab', $tab);
        }
    }

    // ----- Notifications (+ cascade to sends) -----

    public function test_deleting_notifications_before_a_date_removes_only_older_ones_and_their_send_history(): void
    {
        $admin = $this->admin();

        $old = Notification::factory()->create(['created_at' => now()->subDays(10)]);
        $recent = Notification::factory()->create(['created_at' => now()->subDay()]);

        $oldSend = NotificationSend::factory()->create(['notification_id' => $old->id]);
        $recentSend = NotificationSend::factory()->create(['notification_id' => $recent->id]);

        $response = $this->actingAs($admin)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $response->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'notifications']));
        $this->assertModelMissing($old);
        $this->assertModelMissing($oldSend);
        $this->assertModelExists($recent);
        $this->assertModelExists($recentSend);
    }

    public function test_scorer_cannot_delete_notifications(): void
    {
        $scorer = $this->scorer();
        $notification = Notification::factory()->create(['created_at' => now()->subDays(10)]);

        $this->actingAs($scorer)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertModelExists($notification);
    }

    public function test_future_before_date_is_rejected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('before_date');
    }

    /**
     * Phase 3.49 — the cutoff is interpreted in system.display_timezone,
     * not the server's default timezone. Asia/Kolkata is UTC+5:30: a
     * notification created at 2026-01-02 03:00 UTC is already
     * 2026-01-02 08:30 IST — "before 2026-01-02" (IST) must exclude it,
     * while "before 2026-01-03" (IST) must include it.
     */
    public function test_cutoff_date_is_interpreted_in_the_configured_display_timezone(): void
    {
        app(SettingsService::class)->set('system.display_timezone', 'Asia/Kolkata');
        $admin = $this->admin();

        $justAfterIstMidnight = Notification::factory()->create(['created_at' => '2026-01-02 03:00:00']);

        $this->actingAs($admin)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => '2026-01-02',
        ]);
        $this->assertModelExists($justAfterIstMidnight, 'the selected date itself must not be included');

        $this->actingAs($admin)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => '2026-01-03',
        ]);
        $this->assertModelMissing($justAfterIstMidnight);
    }

    public function test_deleting_notifications_writes_an_audit_log_entry(): void
    {
        $admin = $this->admin();
        Notification::factory()->create(['created_at' => now()->subDays(10)]);

        $this->actingAs($admin)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $log = DataCleanupLog::first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_user_id);
        $this->assertSame('notifications', $log->category);
        $this->assertSame('delete_notifications_before', $log->action);
        $this->assertSame(1, $log->records_affected);
        $this->assertNull($log->files_deleted);
    }

    // ----- Notification sends (independent of their parent) -----

    public function test_deleting_notification_sends_before_a_date_leaves_the_parent_notification_intact(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();

        $oldSend = NotificationSend::factory()->create(['notification_id' => $notification->id, 'created_at' => now()->subDays(10)]);
        $recentSend = NotificationSend::factory()->create(['notification_id' => $notification->id, 'created_at' => now()->subDay()]);

        $this->actingAs($admin)->delete(route('admin.data-cleanup.notification-sends.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertModelMissing($oldSend);
        $this->assertModelExists($recentSend);
        $this->assertModelExists($notification);
    }

    // ----- Preview accuracy -----

    public function test_preview_count_matches_what_deletion_actually_removes(): void
    {
        $admin = $this->admin();
        Notification::factory()->count(3)->create(['created_at' => now()->subDays(10)]);
        Notification::factory()->create(['created_at' => now()->subDay()]);

        $preview = $this->actingAs($admin)->getJson(route('admin.data-cleanup.preview.cutoff', [
            'category' => 'notifications',
            'before_date' => now()->subDays(5)->toDateString(),
        ]));

        $preview->assertOk()->assertJson(['count' => 3]);

        $this->actingAs($admin)->delete(route('admin.data-cleanup.notifications.destroy'), [
            'before_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->assertSame(1, Notification::count());
    }
}
