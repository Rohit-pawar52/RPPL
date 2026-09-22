<?php

namespace Tests\Feature\Admin;

use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B5 — bulk, date/count-based retention cleanup for notifications,
 * notification_sends, and fcm_tokens. Admin-triggered only, never
 * automatic; proves authorization, the exact cutoff/retention boundary,
 * the notification->sends RESTRICT-FK cascade handling, and validation.
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

        $response->assertRedirect(route('admin.data-cleanup.index'));
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

    // ----- FCM tokens (keep latest N) -----

    public function test_keeping_latest_fcm_tokens_deletes_the_least_recently_active_beyond_the_limit(): void
    {
        $admin = $this->admin();

        // 10 is the smallest preset retention option (see
        // DeleteFcmTokensRequest::KEEP_COUNT_OPTIONS) — 10 recently
        // active tokens plus 2 stale ones beyond that limit.
        $recent = FcmToken::factory()->count(10)->sequence(
            fn ($sequence) => ['last_seen_at' => now()->subMinutes($sequence->index)],
        )->create();
        $stale = FcmToken::factory()->count(2)->sequence(
            fn ($sequence) => ['last_seen_at' => now()->subDays(30 + $sequence->index)],
        )->create();

        $response = $this->actingAs($admin)->delete(route('admin.data-cleanup.fcm-tokens.destroy'), [
            'keep_count' => 10,
        ]);

        $response->assertRedirect(route('admin.data-cleanup.index'));
        $this->assertSame(10, FcmToken::count());
        $recent->each(fn (FcmToken $token) => $this->assertModelExists($token));
        $stale->each(fn (FcmToken $token) => $this->assertModelMissing($token));
    }

    public function test_keep_count_must_be_one_of_the_preset_options(): void
    {
        $admin = $this->admin();
        FcmToken::factory()->count(3)->create();

        $response = $this->actingAs($admin)->delete(route('admin.data-cleanup.fcm-tokens.destroy'), [
            'keep_count' => 3, // not in the preset list
        ]);

        $response->assertSessionHasErrors('keep_count');
        $this->assertSame(3, FcmToken::count());
    }
}
