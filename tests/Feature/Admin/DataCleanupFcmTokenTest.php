<?php

namespace Tests\Feature\Admin;

use App\Models\DataCleanupLog;
use App\Models\FcmToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.49 — replaces the old "keep latest N" FCM cleanup (conceptually
 * weak: a recent invalid token could survive while an older active one
 * was removed) with two explicit, meaningful operations: delete every
 * inactive token, and delete tokens not seen within N days.
 */
class DataCleanupFcmTokenTest extends TestCase
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

    public function test_deleting_inactive_tokens_removes_only_inactive_ones(): void
    {
        $inactive = FcmToken::factory()->count(2)->create(['is_active' => false]);
        $active = FcmToken::factory()->create(['is_active' => true, 'last_seen_at' => now()]);

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.fcm-tokens.destroy-inactive'));

        $response->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'notifications']));
        $inactive->each(fn (FcmToken $token) => $this->assertModelMissing($token));
        $this->assertModelExists($active);

        $log = DataCleanupLog::first();
        $this->assertSame('fcm_tokens', $log->category);
        $this->assertSame('delete_inactive_fcm_tokens', $log->action);
        $this->assertSame(2, $log->records_affected);
    }

    public function test_a_recently_active_token_survives_inactive_cleanup_even_if_marked_inactive_is_false_edge_case(): void
    {
        // An active token, regardless of how recently seen, must never
        // be touched by the inactive-only cleanup.
        $active = FcmToken::factory()->create(['is_active' => true, 'last_seen_at' => now()->subYears(2)]);

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.fcm-tokens.destroy-inactive'));

        $this->assertModelExists($active);
    }

    public function test_deleting_stale_tokens_removes_tokens_not_seen_within_the_threshold_regardless_of_status(): void
    {
        $stale = FcmToken::factory()->create(['is_active' => true, 'last_seen_at' => now()->subDays(45)]);
        $recent = FcmToken::factory()->create(['is_active' => true, 'last_seen_at' => now()->subDays(1)]);
        $staleInactive = FcmToken::factory()->create(['is_active' => false, 'last_seen_at' => now()->subDays(90)]);

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.fcm-tokens.destroy-stale'), [
            'days' => 30,
        ]);

        $response->assertRedirect(route('admin.data-cleanup.index', ['tab' => 'notifications']));
        $this->assertModelMissing($stale);
        $this->assertModelMissing($staleInactive);
        $this->assertModelExists($recent);
    }

    public function test_stale_days_must_be_one_of_the_preset_options(): void
    {
        FcmToken::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.data-cleanup.fcm-tokens.destroy-stale'), [
            'days' => 5, // not in the preset list
        ]);

        $response->assertSessionHasErrors('days');
        $this->assertSame(1, FcmToken::count());
    }

    public function test_scorer_cannot_delete_fcm_tokens(): void
    {
        $token = FcmToken::factory()->create(['is_active' => false]);

        $this->actingAs($this->scorer())
            ->delete(route('admin.data-cleanup.fcm-tokens.destroy-inactive'))
            ->assertForbidden();

        $this->actingAs($this->scorer())
            ->delete(route('admin.data-cleanup.fcm-tokens.destroy-stale'), ['days' => 30])
            ->assertForbidden();

        $this->assertModelExists($token);
    }

    public function test_stale_preview_count_matches_what_deletion_actually_removes(): void
    {
        FcmToken::factory()->count(3)->create(['last_seen_at' => now()->subDays(45)]);
        FcmToken::factory()->create(['last_seen_at' => now()->subDays(1)]);

        $preview = $this->actingAs($this->admin())
            ->getJson(route('admin.data-cleanup.preview.stale-fcm-tokens', ['days' => 30]));
        $preview->assertOk()->assertJson(['count' => 3]);

        $this->actingAs($this->admin())->delete(route('admin.data-cleanup.fcm-tokens.destroy-stale'), ['days' => 30]);

        $this->assertSame(1, FcmToken::count());
    }

    public function test_stale_preview_is_forbidden_for_scorer(): void
    {
        $this->actingAs($this->scorer())
            ->getJson(route('admin.data-cleanup.preview.stale-fcm-tokens', ['days' => 30]))
            ->assertForbidden();
    }
}
