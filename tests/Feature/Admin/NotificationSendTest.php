<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendNotificationJob;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase B4 — the admin Send/Resend action. Never sends a real Firebase
 * message (SendNotificationJob's own body is queued/faked here, never
 * actually run) — this file proves authorization, snapshot/sent_by
 * correctness across edits, after-commit dispatch semantics, and the
 * admin UI's terminology/token-privacy — not the job's internal Firebase
 * behavior (see NotificationSendJobTest for that).
 */
class NotificationSendTest extends TestCase
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

    public function test_admin_can_trigger_send(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $notification = Notification::factory()->create();

        $response = $this->actingAs($admin)->post(route('admin.notifications.send', $notification));

        $response->assertRedirect(route('admin.notifications.show', $notification));
        $this->assertSame(1, NotificationSend::count());
        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_scorer_cannot_trigger_send(): void
    {
        $scorer = $this->scorer();
        $notification = Notification::factory()->create();

        $this->actingAs($scorer)->post(route('admin.notifications.send', $notification))->assertForbidden();
        $this->assertSame(0, NotificationSend::count());
    }

    public function test_guest_cannot_trigger_send(): void
    {
        $notification = Notification::factory()->create();

        $this->post(route('admin.notifications.send', $notification))->assertRedirect(route('admin.login'));
        $this->assertSame(0, NotificationSend::count());
    }

    // ----- Snapshot / sent_by (D, E) -----

    public function test_send_creates_one_snapshot_from_current_master_content(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $notification = Notification::factory()->create([
            'title' => 'Match tonight',
            'message' => 'The match starts at 7 PM.',
            'action_url' => '/matches/1',
        ]);

        $this->actingAs($admin)->post(route('admin.notifications.send', $notification));

        $send = NotificationSend::firstOrFail();
        $this->assertSame($notification->id, $send->notification_id);
        $this->assertSame('Match tonight', $send->title_snapshot);
        $this->assertSame('The match starts at 7 PM.', $send->message_snapshot);
        $this->assertSame('/matches/1', $send->action_url_snapshot);
        $this->assertSame(0, $send->attempted_count);
        $this->assertNull($send->completed_at);
    }

    public function test_sent_by_is_the_authenticated_admin_and_cannot_be_spoofed(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $someoneElse = $this->admin();
        $notification = Notification::factory()->create();

        $this->actingAs($admin)->post(route('admin.notifications.send', $notification), [
            'sent_by' => $someoneElse->id,
        ]);

        $this->assertSame($admin->id, NotificationSend::firstOrFail()->sent_by);
    }

    // ----- Edit-after-send immutability (F, G, H) -----

    public function test_first_send_snapshot_remains_unchanged_after_master_edit(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $notification = Notification::factory()->create(['title' => 'Match at 7 PM']);

        $this->actingAs($admin)->post(route('admin.notifications.send', $notification));
        $firstSend = NotificationSend::firstOrFail();

        $notification->update(['title' => 'Match at 7:30 PM']);

        $this->assertSame('Match at 7 PM', $firstSend->fresh()->title_snapshot);
    }

    public function test_resend_creates_a_new_second_snapshot_using_edited_content(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $notification = Notification::factory()->create(['title' => 'Match at 7 PM']);

        $this->actingAs($admin)->post(route('admin.notifications.send', $notification));
        $notification->update(['title' => 'Match at 7:30 PM']);
        $this->actingAs($admin)->post(route('admin.notifications.send', $notification));

        $this->assertSame(2, NotificationSend::count());
        $this->assertSame('Match at 7:30 PM', NotificationSend::latest('id')->first()->title_snapshot);
    }

    public function test_previous_snapshot_remains_unchanged_after_resend(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $notification = Notification::factory()->create(['title' => 'Match at 7 PM']);

        $this->actingAs($admin)->post(route('admin.notifications.send', $notification));
        $first = NotificationSend::firstOrFail();

        $notification->update(['title' => 'Match at 7:30 PM']);
        $this->actingAs($admin)->post(route('admin.notifications.send', $notification));

        $this->assertSame('Match at 7 PM', $first->fresh()->title_snapshot);
    }

    // ----- After-commit dispatch (I) -----

    /**
     * Forces a genuine dispatch-time failure (not a mock) via an
     * invalid queue connection — the same technique
     * PlayerRegistrationTest uses for GuestPlayerRegistrationService's
     * own after-commit dispatch. The NotificationSend row surviving
     * proves dispatch happens strictly after, and independently of, the
     * already-committed write.
     */
    public function test_job_is_dispatched_only_after_the_send_row_is_persisted(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();

        config(['queue.default' => 'nonexistent-connection']);

        $response = $this->actingAs($admin)->post(route('admin.notifications.send', $notification));

        $this->assertSame(1, NotificationSend::count());
        $response->assertRedirect(route('admin.notifications.show', $notification));
        $response->assertSessionHas('error');
    }

    // ----- UI privacy/terminology (W, X, Y) -----

    public function test_raw_fcm_tokens_are_never_rendered_in_admin_notification_pages(): void
    {
        $admin = $this->admin();
        FcmToken::factory()->create(['token' => 'super-secret-token-value']);
        $notification = Notification::factory()->create();
        NotificationSend::factory()->create(['notification_id' => $notification->id]);

        $this->actingAs($admin)->get(route('admin.notifications.index'))->assertDontSee('super-secret-token-value');
        $this->actingAs($admin)->get(route('admin.notifications.show', $notification))->assertDontSee('super-secret-token-value');
    }

    public function test_ui_shows_send_button_with_no_history_and_resend_with_history(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();

        $this->actingAs($admin)->get(route('admin.notifications.show', $notification))
            ->assertSee('Send Notification')
            ->assertDontSee('Resend Notification');

        NotificationSend::factory()->create(['notification_id' => $notification->id]);

        $this->actingAs($admin)->get(route('admin.notifications.show', $notification))
            ->assertSee('Resend Notification');
    }

    public function test_send_ui_uses_accepted_terminology_never_delivered_read_or_seen(): void
    {
        $admin = $this->admin();
        $notification = Notification::factory()->create();
        NotificationSend::factory()->create(['notification_id' => $notification->id]);

        $response = $this->actingAs($admin)->get(route('admin.notifications.show', $notification));

        $response->assertSee('Accepted');
        $response->assertDontSee('Delivered');
        $response->assertDontSee('Read');
        $response->assertDontSee('Seen');
    }
}
