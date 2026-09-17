<?php

namespace Tests\Feature\Admin;

use App\Models\GameMatch;
use App\Models\Role;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueManagementTest extends TestCase
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

    public function test_guest_cannot_access_venues(): void
    {
        $this->get(route('admin.venues.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_is_forbidden_from_venue_management(): void
    {
        $scorer = $this->scorer();
        $venue = Venue::factory()->create();

        $this->actingAs($scorer)->get(route('admin.venues.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.venues.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.venues.store'), ['name' => 'Hacked Stadium'])->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.venues.show', $venue))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.venues.edit', $venue))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.venues.update', $venue), [
            'name' => 'Hacked', 'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.venues.destroy', $venue))->assertForbidden();
    }

    public function test_admin_can_access_venue_management(): void
    {
        $admin = $this->admin();
        $venue = Venue::factory()->create();

        $this->actingAs($admin)->get(route('admin.venues.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.venues.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.venues.show', $venue))->assertOk();
        $this->actingAs($admin)->get(route('admin.venues.edit', $venue))->assertOk();
    }

    // ----- Create -----

    public function test_admin_can_create_venue(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.venues.store'), [
            'name' => 'Eden Gardens',
            'city' => 'Kolkata',
            'country' => 'India',
        ]);

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('venues', ['name' => 'Eden Gardens', 'city' => 'Kolkata']);
    }

    public function test_new_venue_defaults_active(): void
    {
        $this->actingAs($this->admin())->post(route('admin.venues.store'), ['name' => 'Default Active Venue']);

        $venue = Venue::where('name', 'Default Active Venue')->firstOrFail();

        $this->assertTrue($venue->is_active);
    }

    public function test_venue_validation_requires_name(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.venues.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('venues', 0);
    }

    // ----- Update -----

    public function test_admin_can_update_venue(): void
    {
        $venue = Venue::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin())->put(route('admin.venues.update', $venue), [
            'name' => 'New Name',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.venues.index'));
        $this->assertDatabaseHas('venues', ['id' => $venue->id, 'name' => 'New Name']);
    }

    public function test_admin_can_deactivate_venue(): void
    {
        $venue = Venue::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'is_active' => 0,
        ]);

        $this->assertFalse($venue->fresh()->is_active);
    }

    public function test_admin_can_reactivate_venue(): void
    {
        $venue = Venue::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'is_active' => 1,
        ]);

        $this->assertTrue($venue->fresh()->is_active);
    }

    // ----- Lifecycle -----

    public function test_inactive_venue_remains_visible_in_admin_index(): void
    {
        Venue::factory()->create(['is_active' => false, 'name' => 'Inactive Visible Venue']);

        $this->actingAs($this->admin())
            ->get(route('admin.venues.index'))
            ->assertOk()
            ->assertSee('Inactive Visible Venue');
    }

    public function test_status_filter_works(): void
    {
        Venue::factory()->create(['name' => 'Only Active Venue']);
        Venue::factory()->create(['name' => 'Only Inactive Venue', 'is_active' => false]);

        $activeResponse = $this->actingAs($this->admin())->get(route('admin.venues.index', ['status' => 'active']));
        $activeResponse->assertSee('Only Active Venue')->assertDontSee('Only Inactive Venue');

        $inactiveResponse = $this->actingAs($this->admin())->get(route('admin.venues.index', ['status' => 'inactive']));
        $inactiveResponse->assertSee('Only Inactive Venue')->assertDontSee('Only Active Venue');
    }

    public function test_venue_active_scope_excludes_inactive_venues(): void
    {
        Venue::factory()->count(2)->create();
        Venue::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(2, Venue::active()->count());
    }

    public function test_plain_venue_query_still_includes_inactive_venues(): void
    {
        Venue::factory()->count(2)->create();
        Venue::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(5, Venue::query()->count());
    }

    public function test_deactivation_preserves_existing_matches(): void
    {
        $venue = Venue::factory()->create();
        GameMatch::factory()->create(['venue_id' => $venue->id]);

        $this->actingAs($this->admin())->put(route('admin.venues.update', $venue), [
            'name' => $venue->name,
            'is_active' => 0,
        ]);

        $this->assertSame(1, $venue->fresh()->matches()->count());
    }

    // ----- Delete -----

    public function test_unused_venue_can_be_deleted(): void
    {
        $venue = Venue::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.venues.destroy', $venue));

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('venues', ['id' => $venue->id]);
    }

    public function test_venue_with_match_history_cannot_be_deleted(): void
    {
        $venue = Venue::factory()->create();
        GameMatch::factory()->create(['venue_id' => $venue->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.venues.destroy', $venue));

        $response->assertRedirect(route('admin.venues.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
    }

    public function test_blocked_deletion_preserves_venue(): void
    {
        $venue = Venue::factory()->create(['name' => 'Protected Venue']);
        GameMatch::factory()->create(['venue_id' => $venue->id]);

        $this->actingAs($this->admin())->delete(route('admin.venues.destroy', $venue));

        $this->assertDatabaseHas('venues', ['id' => $venue->id, 'name' => 'Protected Venue']);
    }

    public function test_inactive_venue_with_history_still_cannot_be_deleted(): void
    {
        $venue = Venue::factory()->create(['is_active' => false]);
        GameMatch::factory()->create(['venue_id' => $venue->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.venues.destroy', $venue));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
    }

    public function test_unauthorized_delete_is_blocked(): void
    {
        $venue = Venue::factory()->create();

        $this->delete(route('admin.venues.destroy', $venue))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('venues', ['id' => $venue->id]);
    }

    // ----- Listing -----

    public function test_search_works(): void
    {
        Venue::factory()->create(['name' => 'Alpha Stadium']);
        Venue::factory()->create(['name' => 'Beta Stadium']);

        $response = $this->actingAs($this->admin())->get(route('admin.venues.index', ['search' => 'Alpha']));

        $response->assertSee('Alpha Stadium')->assertDontSee('Beta Stadium');
    }

    public function test_match_count_renders(): void
    {
        $venue = Venue::factory()->create();
        GameMatch::factory()->count(2)->create(['venue_id' => $venue->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.venues.show', $venue));

        $response->assertOk();
        $response->assertSee('2');
    }

    public function test_pagination_preserves_filters(): void
    {
        Venue::factory()->count(20)->create(['is_active' => false]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.venues.index', ['status' => 'inactive', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('status=inactive', false);
    }

    // ----- UI -----

    public function test_admin_sidebar_contains_venues_link(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.venues.index'), false);
    }

    public function test_scorer_sidebar_does_not_expose_venue_management(): void
    {
        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.venues.index'), false);
    }
}
