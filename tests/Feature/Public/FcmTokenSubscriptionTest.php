<?php

namespace Tests\Feature\Public;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase B1 — anonymous FCM token subscription. No browser/Firebase
 * integration exists yet; this only proves the backend data foundation
 * (upsert semantics, ownership preservation, privacy, rate limiting).
 */
class FcmTokenSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_guest_can_register_an_fcm_token(): void
    {
        $token = Str::random(163);

        $response = $this->postJson(route('public.notifications.subscribe'), ['token' => $token]);

        $response->assertCreated();
        $response->assertExactJson(['subscribed' => true]);
        $this->assertDatabaseHas('fcm_tokens', ['token' => $token]);
    }

    public function test_stored_row_has_no_owner_and_is_active_with_last_seen_at(): void
    {
        $token = Str::random(163);

        $this->postJson(route('public.notifications.subscribe'), ['token' => $token]);

        $fcmToken = FcmToken::where('token', $token)->firstOrFail();

        $this->assertNull($fcmToken->user_id);
        $this->assertNull($fcmToken->player_id);
        $this->assertTrue($fcmToken->is_active);
        $this->assertNotNull($fcmToken->last_seen_at);
    }

    public function test_submitting_the_same_token_again_does_not_create_a_duplicate_and_refreshes_last_seen_at(): void
    {
        $token = Str::random(163);
        $fcmToken = FcmToken::factory()->create(['token' => $token, 'last_seen_at' => now()->subDays(3)]);

        $response = $this->postJson(route('public.notifications.subscribe'), ['token' => $token]);

        $response->assertOk();
        $response->assertExactJson(['subscribed' => true]);
        $this->assertSame(1, FcmToken::where('token', $token)->count());
        $this->assertTrue($fcmToken->fresh()->last_seen_at->gt(now()->subMinute()));
    }

    public function test_resubmitting_an_inactive_token_reactivates_it(): void
    {
        $token = Str::random(163);
        FcmToken::factory()->inactive()->create(['token' => $token]);

        $this->postJson(route('public.notifications.subscribe'), ['token' => $token]);

        $this->assertTrue(FcmToken::where('token', $token)->firstOrFail()->is_active);
    }

    /**
     * The core future-proofing invariant: an anonymous re-subscription of
     * an already-owned token must never clear that ownership, even
     * though nothing in this phase ever sets it in the first place.
     */
    public function test_anonymous_resubscription_does_not_clear_an_existing_owner_association(): void
    {
        $user = User::factory()->create();
        $token = Str::random(163);
        FcmToken::factory()->create(['token' => $token, 'user_id' => $user->id]);

        $this->postJson(route('public.notifications.subscribe'), ['token' => $token]);

        $this->assertSame($user->id, FcmToken::where('token', $token)->firstOrFail()->user_id);
    }

    public function test_missing_token_is_rejected(): void
    {
        $response = $this->postJson(route('public.notifications.subscribe'), []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('token');
        $this->assertSame(0, FcmToken::count());
    }

    public function test_overlong_token_is_rejected(): void
    {
        $response = $this->postJson(route('public.notifications.subscribe'), ['token' => Str::random(513)]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('token');
    }

    public function test_subscribe_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('public.notifications.subscribe'), ['token' => Str::random(163)])->assertSuccessful();
        }

        $this->postJson(route('public.notifications.subscribe'), ['token' => Str::random(163)])
            ->assertStatus(429);
    }

    public function test_response_never_exposes_the_token_or_internal_identifiers(): void
    {
        $token = Str::random(163);

        $response = $this->postJson(route('public.notifications.subscribe'), ['token' => $token]);

        $response->assertExactJson(['subscribed' => true]);
    }
}
