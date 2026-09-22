<?php

namespace Tests\Feature\Admin;

use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase B3 — admin notification-CONTENT management CRUD. No Send/Resend
 * action exists yet (a later phase); this file proves authorization,
 * validation (especially action_url's internal-path-only rule),
 * created_by immutability, send-history presentation/read-only-ness, and
 * the active-subscriber count — not exhaustive CRUD framework behavior.
 */
class NotificationManagementTest extends TestCase
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

    // ----- Authorization (A, B, C) -----

    public function test_admin_can_access_notification_management(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();

        $this->actingAs($admin)->get(route('admin.notifications.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.notifications.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.notifications.show', $notification))->assertOk();
        $this->actingAs($admin)->get(route('admin.notifications.edit', $notification))->assertOk();
    }

    public function test_scorer_cannot_access_notification_management(): void
    {
        $scorer = $this->scorer();
        $notification = Notification::factory()->create();

        $this->actingAs($scorer)->get(route('admin.notifications.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.notifications.create'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.notifications.show', $notification))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.notifications.edit', $notification))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.notifications.store'), [
            'title' => 'Hacked', 'message' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_guest_cannot_access_notification_management(): void
    {
        $notification = Notification::factory()->create();

        $this->get(route('admin.notifications.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.notifications.show', $notification))->assertRedirect(route('admin.login'));
    }

    // ----- Create / created_by (D, E) -----

    public function test_admin_can_create_a_notification(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.notifications.store'), [
            'title' => 'Registration is now open',
            'message' => 'RPPL 2026 player registration is now open. Register today!',
            'action_url' => '/player-registration',
        ]);

        $response->assertRedirect(route('admin.notifications.index'));

        $notification = Notification::firstWhere('title', 'Registration is now open');
        $this->assertNotNull($notification);
        $this->assertSame('/player-registration', $notification->action_url);
        $this->assertSame($admin->id, $notification->created_by);
    }

    public function test_created_by_comes_from_the_authenticated_admin_and_cannot_be_spoofed(): void
    {
        $admin = $this->admin();
        $someoneElse = $this->admin();

        $this->actingAs($admin)->post(route('admin.notifications.store'), [
            'title' => 'Final match tomorrow',
            'message' => 'The final is tomorrow at 6 PM.',
            'created_by' => $someoneElse->id,
        ]);

        $notification = Notification::firstWhere('title', 'Final match tomorrow');
        $this->assertSame($admin->id, $notification->created_by);
    }

    // ----- Update / created_by immutability (F, G) -----

    public function test_admin_can_update_a_notification(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create(['title' => 'Original title']);

        $response = $this->actingAs($admin)->put(route('admin.notifications.update', $notification), [
            'title' => 'Updated title',
            'message' => 'Updated message.',
            'action_url' => '/matches',
        ]);

        $response->assertRedirect(route('admin.notifications.index'));
        $notification->refresh();
        $this->assertSame('Updated title', $notification->title);
        $this->assertSame('/matches', $notification->action_url);
    }

    public function test_update_does_not_change_created_by(): void
    {
        $originalCreator = $this->admin();
        $editingAdmin = $this->admin();
        $notification = Notification::factory()->create(['created_by' => $originalCreator->id]);

        $this->actingAs($editingAdmin)->put(route('admin.notifications.update', $notification), [
            'title' => 'Edited by someone else',
            'message' => 'Message.',
            'created_by' => $editingAdmin->id,
        ]);

        $this->assertSame($originalCreator->id, $notification->fresh()->created_by);
    }

    // ----- action_url validation (H, I, J) -----

    public function test_valid_internal_action_urls_are_accepted(): void
    {
        $admin = $this->admin();

        foreach (['/', '/matches/123', '/player-registration', '/editions/1'] as $url) {
            $response = $this->actingAs($admin)->post(route('admin.notifications.store'), [
                'title' => 'Notification for '.$url,
                'message' => 'Message.',
                'action_url' => $url,
            ]);

            $response->assertSessionDoesntHaveErrors('action_url');
            $this->assertSame($url, Notification::firstWhere('title', 'Notification for '.$url)->action_url);
        }
    }

    public function test_invalid_external_or_dangerous_action_urls_are_rejected(): void
    {
        $admin = $this->admin();

        $dangerous = [
            '//evil.example',
            'https://evil.example',
            'http://evil.example',
            'javascript:alert(1)',
            'data:text/html,evil',
            "/matches/1\n/evil",
        ];

        foreach ($dangerous as $url) {
            $response = $this->actingAs($admin)->post(route('admin.notifications.store'), [
                'title' => 'Should not save',
                'message' => 'Message.',
                'action_url' => $url,
            ]);

            $response->assertSessionHasErrors('action_url');
        }

        $this->assertSame(0, Notification::count());
    }

    public function test_blank_action_url_is_normalized_to_null(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.notifications.store'), [
            'title' => 'No link',
            'message' => 'Message.',
            'action_url' => '   ',
        ]);

        $notification = Notification::firstWhere('title', 'No link');
        $this->assertNotNull($notification);
        $this->assertNull($notification->action_url);
    }

    // ----- No destroy route (K) -----

    public function test_notification_cannot_be_destroyed_because_no_destroy_route_exists(): void
    {
        $this->assertFalse(Route::has('admin.notifications.destroy'));
    }

    // ----- Show page / send history (L, M, N) -----

    public function test_show_page_displays_send_history_snapshots(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();
        NotificationSend::factory()->create([
            'notification_id' => $notification->id,
            'title_snapshot' => 'Snapshot Title',
            'message_snapshot' => 'Snapshot message body.',
            'attempted_count' => 10,
            'success_count' => 8,
            'failure_count' => 2,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.notifications.show', $notification));

        $response->assertOk();
        $response->assertSee('Snapshot Title');
        $response->assertSee('Snapshot message body.');
        $response->assertSee('8'); // Accepted count
    }

    public function test_editing_notification_master_does_not_mutate_historical_send_snapshots(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create(['title' => 'Original']);
        $send = NotificationSend::factory()->create([
            'notification_id' => $notification->id,
            'title_snapshot' => 'Original',
        ]);

        $this->actingAs($admin)->put(route('admin.notifications.update', $notification), [
            'title' => 'Edited',
            'message' => 'Edited message.',
        ]);

        $this->assertSame('Original', $send->fresh()->title_snapshot);
        $this->assertSame('Edited', $notification->fresh()->title);
    }

    public function test_show_page_uses_accepted_terminology_rather_than_delivered(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();
        NotificationSend::factory()->create(['notification_id' => $notification->id]);

        $response = $this->actingAs($admin)->get(route('admin.notifications.show', $notification));

        $response->assertOk();
        $response->assertSee('Accepted');
        $response->assertDontSee('Delivered');
    }

    // ----- Active subscriber count (O) -----

    public function test_active_subscriber_count_counts_only_active_fcm_token_rows(): void
    {
        $admin = $this->admin();
        FcmToken::factory()->count(3)->create(['is_active' => true]);
        FcmToken::factory()->count(2)->inactive()->create();

        $response = $this->actingAs($admin)->get(route('admin.notifications.index'));

        $response->assertOk();
        $response->assertSee('3');
    }
}
