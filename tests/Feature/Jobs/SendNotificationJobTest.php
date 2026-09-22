<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendNotificationJob;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Services\Notification\FcmMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase B4 — SendNotificationJob's own send/count/deactivation logic.
 * FcmMessagingService is mocked throughout (via container binding,
 * matching ProcessPaymentProofOcrTest's own pattern) so this suite never
 * depends on real Firebase credentials or sends a real message.
 */
class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMessaging(array $result): Mockery\MockInterface
    {
        $mock = Mockery::mock(FcmMessagingService::class);
        $mock->shouldReceive('sendToTokens')->once()->andReturn($result);
        $this->app->instance(FcmMessagingService::class, $mock);

        return $mock;
    }

    public function test_zero_active_tokens_completes_without_calling_firebase(): void
    {
        $mock = Mockery::mock(FcmMessagingService::class);
        $mock->shouldNotReceive('sendToTokens');
        $this->app->instance(FcmMessagingService::class, $mock);

        $send = NotificationSend::factory()->create();

        (new SendNotificationJob($send))->handle();

        $send->refresh();
        $this->assertSame(0, $send->attempted_count);
        $this->assertSame(0, $send->success_count);
        $this->assertSame(0, $send->failure_count);
        $this->assertNotNull($send->completed_at);
    }

    public function test_inactive_tokens_are_excluded_and_active_tokens_are_attempted(): void
    {
        FcmToken::factory()->inactive()->create(['token' => 'inactive-token']);
        FcmToken::factory()->create(['token' => 'active-token-1']);
        FcmToken::factory()->create(['token' => 'active-token-2']);

        $captured = null;
        $mock = Mockery::mock(FcmMessagingService::class);
        $mock->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function (array $tokens) use (&$captured) {
                $captured = $tokens;

                return true;
            })
            ->andReturn(['attempted' => 2, 'success' => 2, 'failure' => 0, 'invalid_tokens' => []]);
        $this->app->instance(FcmMessagingService::class, $mock);

        (new SendNotificationJob(NotificationSend::factory()->create()))->handle();

        $this->assertCount(2, $captured);
        $this->assertNotContains('inactive-token', $captured);
        $this->assertContains('active-token-1', $captured);
        $this->assertContains('active-token-2', $captured);
    }

    public function test_successful_result_increments_accepted_and_persists_counts(): void
    {
        FcmToken::factory()->create();
        $this->fakeMessaging(['attempted' => 1, 'success' => 1, 'failure' => 0, 'invalid_tokens' => []]);

        $send = NotificationSend::factory()->create();
        (new SendNotificationJob($send))->handle();

        $send->refresh();
        $this->assertSame(1, $send->attempted_count);
        $this->assertSame(1, $send->success_count);
        $this->assertSame(0, $send->failure_count);
        $this->assertNotNull($send->completed_at);
    }

    public function test_failures_increment_failure_count(): void
    {
        FcmToken::factory()->create();
        $this->fakeMessaging(['attempted' => 1, 'success' => 0, 'failure' => 1, 'invalid_tokens' => []]);

        $send = NotificationSend::factory()->create();
        (new SendNotificationJob($send))->handle();

        $this->assertSame(1, $send->fresh()->failure_count);
    }

    public function test_permanently_invalid_tokens_are_deactivated(): void
    {
        $token = FcmToken::factory()->create(['token' => 'dead-token']);
        $this->fakeMessaging(['attempted' => 1, 'success' => 0, 'failure' => 1, 'invalid_tokens' => ['dead-token']]);

        (new SendNotificationJob(NotificationSend::factory()->create()))->handle();

        $this->assertFalse($token->fresh()->is_active);
    }

    public function test_transient_failure_tokens_remain_active(): void
    {
        $token = FcmToken::factory()->create(['token' => 'flaky-token']);
        $this->fakeMessaging(['attempted' => 1, 'success' => 0, 'failure' => 1, 'invalid_tokens' => []]);

        (new SendNotificationJob(NotificationSend::factory()->create()))->handle();

        $this->assertTrue($token->fresh()->is_active);
    }

    public function test_attempted_equals_accepted_plus_failed(): void
    {
        FcmToken::factory()->count(5)->create();
        $this->fakeMessaging(['attempted' => 5, 'success' => 3, 'failure' => 2, 'invalid_tokens' => []]);

        $send = NotificationSend::factory()->create();
        (new SendNotificationJob($send))->handle();

        $send->refresh();
        $this->assertSame($send->attempted_count, $send->success_count + $send->failure_count);
    }

    public function test_completed_at_is_populated_after_normal_completion(): void
    {
        FcmToken::factory()->create();
        $this->fakeMessaging(['attempted' => 1, 'success' => 1, 'failure' => 0, 'invalid_tokens' => []]);

        $send = NotificationSend::factory()->create();
        $this->assertNull($send->completed_at);

        (new SendNotificationJob($send))->handle();

        $this->assertNotNull($send->fresh()->completed_at);
    }

    public function test_already_completed_send_does_not_send_again(): void
    {
        FcmToken::factory()->create();
        $mock = Mockery::mock(FcmMessagingService::class);
        $mock->shouldNotReceive('sendToTokens');
        $this->app->instance(FcmMessagingService::class, $mock);

        $send = NotificationSend::factory()->create([
            'completed_at' => now(),
            'attempted_count' => 1,
            'success_count' => 1,
        ]);

        (new SendNotificationJob($send))->handle();

        $this->assertSame(1, $send->fresh()->success_count);
    }

    public function test_job_has_no_automatic_retry(): void
    {
        $job = new SendNotificationJob(NotificationSend::factory()->create());

        $this->assertSame(1, $job->tries);
    }

    public function test_action_url_null_becomes_root_path_in_outbound_payload(): void
    {
        FcmToken::factory()->create();

        $capturedActionUrl = null;
        $mock = Mockery::mock(FcmMessagingService::class);
        $mock->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function (array $tokens, string $title, string $body, string $actionUrl) use (&$capturedActionUrl) {
                $capturedActionUrl = $actionUrl;

                return true;
            })
            ->andReturn(['attempted' => 1, 'success' => 1, 'failure' => 0, 'invalid_tokens' => []]);
        $this->app->instance(FcmMessagingService::class, $mock);

        $send = NotificationSend::factory()->create(['action_url_snapshot' => null]);
        (new SendNotificationJob($send))->handle();

        $this->assertSame('/', $capturedActionUrl);
    }

    /**
     * Proves the job uses the SNAPSHOT, not the master content — even
     * when the master was edited between Send being clicked and this
     * job actually running (simulating a delayed queue).
     */
    public function test_job_uses_the_send_snapshot_content_not_the_edited_master(): void
    {
        FcmToken::factory()->create();

        $capturedTitle = null;
        $capturedBody = null;
        $mock = Mockery::mock(FcmMessagingService::class);
        $mock->shouldReceive('sendToTokens')
            ->once()
            ->withArgs(function (array $tokens, string $title, string $body, string $actionUrl) use (&$capturedTitle, &$capturedBody) {
                $capturedTitle = $title;
                $capturedBody = $body;

                return true;
            })
            ->andReturn(['attempted' => 1, 'success' => 1, 'failure' => 0, 'invalid_tokens' => []]);
        $this->app->instance(FcmMessagingService::class, $mock);

        $notification = Notification::factory()->create(['title' => 'Original', 'message' => 'Original message']);
        $send = NotificationSend::factory()->create([
            'notification_id' => $notification->id,
            'title_snapshot' => 'Original',
            'message_snapshot' => 'Original message',
        ]);

        $notification->update(['title' => 'Edited', 'message' => 'Edited message']);

        (new SendNotificationJob($send))->handle();

        $this->assertSame('Original', $capturedTitle);
        $this->assertSame('Original message', $capturedBody);
    }
}
