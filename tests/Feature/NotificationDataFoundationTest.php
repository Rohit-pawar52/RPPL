<?php

namespace Tests\Feature;

use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B1 — schema/model-relationship coverage for the notification
 * data foundation. No admin CRUD or sending exists yet (a later phase),
 * so this is deliberately limited to proving the schema and
 * relationships behave as designed, not exercising a feature that
 * doesn't exist.
 */
class NotificationDataFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fcm_token_can_belong_to_a_user_or_a_player_or_neither(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();

        $guestToken = FcmToken::factory()->create();
        $userToken = FcmToken::factory()->create(['user_id' => $user->id]);
        $playerToken = FcmToken::factory()->create(['player_id' => $player->id]);

        $this->assertNull($guestToken->user_id);
        $this->assertNull($guestToken->player_id);
        $this->assertTrue($userToken->user->is($user));
        $this->assertTrue($playerToken->player->is($player));
    }

    public function test_fcm_token_active_scope_excludes_inactive_tokens(): void
    {
        FcmToken::factory()->create(['is_active' => true]);
        FcmToken::factory()->inactive()->create();

        $this->assertSame(1, FcmToken::active()->count());
    }

    public function test_user_and_player_can_access_their_fcm_tokens(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();

        FcmToken::factory()->create(['user_id' => $user->id]);
        FcmToken::factory()->create(['player_id' => $player->id]);

        $this->assertCount(1, $user->fresh()->fcmTokens);
        $this->assertCount(1, $player->fresh()->fcmTokens);
    }

    /**
     * The core content/history invariant this schema exists to protect:
     * editing a Notification must never rewrite an earlier send's
     * snapshot.
     */
    public function test_notification_send_snapshot_is_independent_of_later_edits_to_the_notification(): void
    {
        $notification = Notification::factory()->create(['title' => 'Original title']);

        $send = NotificationSend::factory()->create([
            'notification_id' => $notification->id,
            'title_snapshot' => $notification->title,
        ]);

        $notification->update(['title' => 'Edited title']);

        $this->assertSame('Original title', $send->fresh()->title_snapshot);
        $this->assertSame('Edited title', $notification->fresh()->title);
    }

    public function test_notification_latest_send_reflects_the_most_recent_send(): void
    {
        $notification = Notification::factory()->create();

        $this->assertNull($notification->latestSend());

        NotificationSend::factory()->create(['notification_id' => $notification->id]);
        $second = NotificationSend::factory()->create(['notification_id' => $notification->id]);

        $this->assertTrue($notification->latestSend()->is($second));
        $this->assertCount(2, $notification->sends);
    }

    public function test_notification_and_notification_send_relationships_resolve(): void
    {
        $creator = User::factory()->create();
        $sender = User::factory()->create();
        $notification = Notification::factory()->create(['created_by' => $creator->id]);
        $send = NotificationSend::factory()->create(['notification_id' => $notification->id, 'sent_by' => $sender->id]);

        $this->assertTrue($notification->creator->is($creator));
        $this->assertTrue($send->notification->is($notification));
        $this->assertTrue($send->sender->is($sender));
        $this->assertTrue($notification->sends->contains($send));
    }
}
