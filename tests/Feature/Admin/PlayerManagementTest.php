<?php

namespace Tests\Feature\Admin;

use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
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

    public function test_guest_cannot_access_players(): void
    {
        $this->get(route('admin.players.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_cannot_list_players(): void
    {
        $this->actingAs($this->scorer())
            ->get(route('admin.players.index'))
            ->assertForbidden();
    }

    public function test_scorer_cannot_create_player(): void
    {
        $scorer = $this->scorer();

        $this->actingAs($scorer)->get(route('admin.players.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.players.store'), ['name' => 'Hacker'])->assertForbidden();
    }

    public function test_scorer_cannot_update_player(): void
    {
        $player = Player::factory()->create();

        // Valid payload (including is_active, now required by the form) so
        // this genuinely tests authorization, not incidentally tripping
        // validation before the controller's authorize() check is reached.
        $this->actingAs($this->scorer())
            ->put(route('admin.players.update', $player), [
                'name' => 'Hacked',
                'is_active' => 1,
            ])
            ->assertForbidden();
    }

    public function test_scorer_cannot_delete_player(): void
    {
        $player = Player::factory()->create();

        $this->actingAs($this->scorer())
            ->delete(route('admin.players.destroy', $player))
            ->assertForbidden();

        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_admin_can_access_player_management(): void
    {
        $admin = $this->admin();
        $player = Player::factory()->create();

        $this->actingAs($admin)->get(route('admin.players.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.players.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.players.show', $player))->assertOk();
        $this->actingAs($admin)->get(route('admin.players.edit', $player))->assertOk();
    }

    // ----- Create -----

    public function test_admin_can_create_valid_player(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Test Player',
            'email' => 'test.player@example.test',
            'phone' => '9123456789',
            'date_of_birth' => '2000-01-01',
            'primary_role' => 'batter',
            'batting_style' => 'right_hand',
            'bowling_style' => 'none',
        ]);

        $response->assertRedirect(route('admin.players.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('players', [
            'name' => 'Test Player',
            'email' => 'test.player@example.test',
            'primary_role' => 'batter',
        ]);
    }

    public function test_player_validation_requires_name(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.players.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('players', 0);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Bad Email Player',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('players', 0);
    }

    public function test_future_date_of_birth_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Future Player',
            'date_of_birth' => now()->addYear()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('date_of_birth');
        $this->assertDatabaseCount('players', 0);
    }

    public function test_invalid_enum_values_are_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Bad Enum Player',
            'primary_role' => 'captain', // not a real enum value
            'batting_style' => 'both_hands', // not a real enum value
            'bowling_style' => 'spin', // not a real enum value
        ]);

        $response->assertSessionHasErrors(['primary_role', 'batting_style', 'bowling_style']);
        $this->assertDatabaseCount('players', 0);
    }

    public function test_photo_can_be_uploaded_and_path_saved(): void
    {
        Storage::fake('public');

        // create() (not image()) — this test environment has no GD extension,
        // and create() only needs a MIME type/extension, not real pixel data,
        // to satisfy the 'image'/'mimes' validation rules.
        $photo = UploadedFile::fake()->create('player.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Photo Player',
            'photo' => $photo,
        ]);

        $response->assertRedirect(route('admin.players.index'));

        $player = Player::where('name', 'Photo Player')->firstOrFail();

        $this->assertNotNull($player->photo_path);
        $this->assertStringStartsWith('players/', $player->photo_path);
        Storage::disk('public')->assertExists($player->photo_path);
    }

    // ----- Update -----

    public function test_admin_can_update_player(): void
    {
        $player = Player::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => 'New Name',
            'email' => $player->email,
            'phone' => $player->phone,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.players.index'));
        $this->assertDatabaseHas('players', ['id' => $player->id, 'name' => 'New Name']);
    }

    public function test_player_can_retain_its_own_phone_and_email_when_updating(): void
    {
        $player = Player::factory()->create(['phone' => '9111111111', 'email' => 'keep@example.test']);

        $response = $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => 'Renamed Player',
            'phone' => '9111111111',
            'email' => 'keep@example.test',
            'is_active' => 1,
        ]);

        $response->assertSessionDoesntHaveErrors(['phone', 'email']);
        $this->assertDatabaseHas('players', ['id' => $player->id, 'name' => 'Renamed Player']);
    }

    public function test_updating_to_a_conflicting_email_fails(): void
    {
        Player::factory()->create(['email' => 'taken@example.test']);
        $player = Player::factory()->create(['email' => 'mine@example.test']);

        $response = $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'email' => 'taken@example.test',
            'is_active' => 1,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('players', ['id' => $player->id, 'email' => 'mine@example.test']);
    }

    public function test_photo_can_be_replaced_and_old_photo_removed(): void
    {
        Storage::fake('public');

        $player = Player::factory()->create(['photo_path' => null]);

        $firstPhoto = UploadedFile::fake()->create('first.jpg', 100, 'image/jpeg');
        $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'photo' => $firstPhoto,
            'is_active' => 1,
        ]);

        $player->refresh();
        $originalPath = $player->photo_path;
        $this->assertNotNull($originalPath);
        Storage::disk('public')->assertExists($originalPath);

        $secondPhoto = UploadedFile::fake()->create('second.jpg', 100, 'image/jpeg');
        $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'photo' => $secondPhoto,
            'is_active' => 1,
        ]);

        $player->refresh();

        $this->assertNotSame($originalPath, $player->photo_path);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($player->photo_path);
    }

    // ----- Delete -----

    public function test_empty_player_can_be_deleted(): void
    {
        $player = Player::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.players.destroy', $player));

        $response->assertRedirect(route('admin.players.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    public function test_player_with_registration_cannot_be_deleted(): void
    {
        $player = Player::factory()->create();
        PlayerRegistration::factory()->create(['player_id' => $player->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.players.destroy', $player));

        $response->assertRedirect(route('admin.players.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_blocked_delete_leaves_player_intact(): void
    {
        $player = Player::factory()->create(['name' => 'Protected Player']);
        PlayerRegistration::factory()->create(['player_id' => $player->id]);

        $this->actingAs($this->admin())->delete(route('admin.players.destroy', $player));

        $this->assertDatabaseHas('players', ['id' => $player->id, 'name' => 'Protected Player']);
    }

    public function test_unauthorized_delete_blocked(): void
    {
        $player = Player::factory()->create();

        $this->delete(route('admin.players.destroy', $player))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_photo_cleanup_works_when_valid_deletion_succeeds(): void
    {
        Storage::fake('public');

        $photo = UploadedFile::fake()->create('deletable.jpg', 100, 'image/jpeg');
        $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Deletable Player',
            'photo' => $photo,
        ]);

        $player = Player::where('name', 'Deletable Player')->firstOrFail();
        $photoPath = $player->photo_path;
        Storage::disk('public')->assertExists($photoPath);

        $this->actingAs($this->admin())->delete(route('admin.players.destroy', $player));

        Storage::disk('public')->assertMissing($photoPath);
        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    // ----- Index / Filtering -----

    public function test_search_works(): void
    {
        Player::factory()->create(['name' => 'Alpha Player']);
        Player::factory()->create(['name' => 'Beta Player']);

        $response = $this->actingAs($this->admin())->get(route('admin.players.index', ['search' => 'Alpha']));

        $response->assertSee('Alpha Player')->assertDontSee('Beta Player');
    }

    public function test_role_filter_works(): void
    {
        Player::factory()->create(['name' => 'Batter Player', 'primary_role' => 'batter']);
        Player::factory()->create(['name' => 'Bowler Player', 'primary_role' => 'bowler']);

        $response = $this->actingAs($this->admin())->get(route('admin.players.index', ['primary_role' => 'bowler']));

        $response->assertSee('Bowler Player')->assertDontSee('Batter Player');
    }

    public function test_pagination_works_and_query_string_survives(): void
    {
        Player::factory()->count(20)->create(['primary_role' => 'all_rounder']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.players.index', ['primary_role' => 'all_rounder', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('primary_role=all_rounder', false);
    }

    public function test_registration_count_displayed_correctly(): void
    {
        $player = Player::factory()->create(['name' => 'Counted Player']);
        PlayerRegistration::factory()->count(2)->create(['player_id' => $player->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.players.show', $player));

        $response->assertOk();
        $response->assertSee('2');
    }

    // ----- UI -----

    public function test_admin_sidebar_contains_players_link(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.players.index'), false);
    }

    public function test_scorer_sidebar_does_not_expose_player_management(): void
    {
        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.players.index'), false);
    }

    // ----- Lifecycle (active/inactive) -----

    public function test_new_player_defaults_active(): void
    {
        $player = Player::factory()->create();

        $this->assertTrue($player->fresh()->is_active);
    }

    public function test_admin_created_player_is_active_without_choosing_it(): void
    {
        $this->actingAs($this->admin())->post(route('admin.players.store'), [
            'name' => 'Default Active Player',
        ]);

        $player = Player::where('name', 'Default Active Player')->firstOrFail();

        $this->assertTrue($player->is_active);
    }

    public function test_admin_can_deactivate_player(): void
    {
        $player = Player::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'is_active' => 0,
        ]);

        $response->assertRedirect(route('admin.players.index'));
        $this->assertFalse($player->fresh()->is_active);
    }

    public function test_admin_can_reactivate_player(): void
    {
        $player = Player::factory()->inactive()->create();

        $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'is_active' => 1,
        ]);

        $this->assertTrue($player->fresh()->is_active);
    }

    public function test_player_index_displays_lifecycle_status(): void
    {
        Player::factory()->create(['name' => 'Active Display Player']);
        Player::factory()->inactive()->create(['name' => 'Inactive Display Player']);

        $response = $this->actingAs($this->admin())->get(route('admin.players.index'));

        $response->assertOk();
        $response->assertSee('Active Display Player');
        $response->assertSee('Inactive Display Player');
        $response->assertSee('Active');
        $response->assertSee('Inactive');
    }

    public function test_status_filter_returns_active_players(): void
    {
        Player::factory()->create(['name' => 'Only Active Player']);
        Player::factory()->inactive()->create(['name' => 'Only Inactive Player']);

        $response = $this->actingAs($this->admin())->get(route('admin.players.index', ['status' => 'active']));

        $response->assertSee('Only Active Player')->assertDontSee('Only Inactive Player');
    }

    public function test_status_filter_returns_inactive_players(): void
    {
        Player::factory()->create(['name' => 'Visible Active Player']);
        Player::factory()->inactive()->create(['name' => 'Visible Inactive Player']);

        $response = $this->actingAs($this->admin())->get(route('admin.players.index', ['status' => 'inactive']));

        $response->assertSee('Visible Inactive Player')->assertDontSee('Visible Active Player');
    }

    public function test_search_and_status_filter_work_together(): void
    {
        Player::factory()->create(['name' => 'Kumar Active']);
        Player::factory()->inactive()->create(['name' => 'Kumar Inactive']);
        Player::factory()->create(['name' => 'Sharma Active']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.players.index', ['search' => 'Kumar', 'status' => 'active']));

        $response->assertSee('Kumar Active')
            ->assertDontSee('Kumar Inactive')
            ->assertDontSee('Sharma Active');
    }

    public function test_pagination_preserves_status_filter(): void
    {
        Player::factory()->count(20)->inactive()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.players.index', ['status' => 'inactive', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('status=inactive', false);
    }

    public function test_inactive_player_remains_viewable_directly_by_admin(): void
    {
        $player = Player::factory()->inactive()->create(['name' => 'Directly Viewable Player']);

        $this->actingAs($this->admin())
            ->get(route('admin.players.show', $player))
            ->assertOk()
            ->assertSee('Directly Viewable Player')
            // The status badge renders the raw word "inactive" and relies
            // on CSS `capitalize` for the visual "Inactive" — assert the
            // actual rendered text, not the styled appearance.
            ->assertSee('inactive');
    }

    public function test_inactive_player_retains_registrations_and_history(): void
    {
        $player = Player::factory()->create();
        PlayerRegistration::factory()->create(['player_id' => $player->id]);

        $player->update(['is_active' => false]);

        $this->assertSame(1, $player->fresh()->playerRegistrations()->count());
    }

    public function test_player_with_history_still_cannot_be_deleted_regardless_of_active_status(): void
    {
        $player = Player::factory()->inactive()->create();
        PlayerRegistration::factory()->create(['player_id' => $player->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.players.destroy', $player));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    public function test_deactivation_does_not_delete_player_registrations(): void
    {
        $player = Player::factory()->create();
        PlayerRegistration::factory()->count(2)->create(['player_id' => $player->id]);

        $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'is_active' => 0,
        ]);

        $this->assertSame(2, $player->fresh()->playerRegistrations()->count());
    }

    public function test_reactivation_does_not_modify_registrations(): void
    {
        $player = Player::factory()->inactive()->create();
        $registration = PlayerRegistration::factory()->create(['player_id' => $player->id]);
        $originalPaymentStatus = $registration->payment_status;

        $this->actingAs($this->admin())->put(route('admin.players.update', $player), [
            'name' => $player->name,
            'is_active' => 1,
        ]);

        $this->assertSame($originalPaymentStatus, $registration->fresh()->payment_status);
        $this->assertSame(1, $player->fresh()->playerRegistrations()->count());
    }

    // ----- Scopes -----

    public function test_player_active_scope_returns_only_active_players(): void
    {
        Player::factory()->count(2)->create();
        Player::factory()->count(3)->inactive()->create();

        $this->assertSame(2, Player::active()->count());
    }

    public function test_plain_player_query_still_includes_inactive_players(): void
    {
        Player::factory()->count(2)->create();
        Player::factory()->count(3)->inactive()->create();

        $this->assertSame(5, Player::query()->count());
    }
}
