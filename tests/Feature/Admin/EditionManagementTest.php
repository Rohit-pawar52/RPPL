<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionManagementTest extends TestCase
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

    public function test_guest_cannot_access_edition_index(): void
    {
        $this->get(route('admin.editions.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_cannot_access_edition_management(): void
    {
        $scorer = $this->scorer();

        $this->actingAs($scorer)->get(route('admin.editions.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.editions.create'))->assertForbidden();

        $edition = Edition::factory()->create();
        $this->actingAs($scorer)->get(route('admin.editions.show', $edition))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.editions.edit', $edition))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.editions.update', $edition), [
            'name' => 'Hacked', 'year' => 2099, 'status' => 'upcoming',
        ])->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.editions.destroy', $edition))->assertForbidden();
    }

    public function test_admin_can_access_edition_index(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.editions.index'))
            ->assertOk()
            ->assertViewIs('admin.editions.index');
    }

    public function test_admin_can_view_create_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.editions.create'))
            ->assertOk()
            ->assertViewIs('admin.editions.create');
    }

    public function test_admin_can_view_edition_details(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.editions.show', $edition))
            ->assertOk()
            ->assertSee($edition->name);
    }

    public function test_admin_can_view_edit_page(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.editions.edit', $edition))
            ->assertOk()
            ->assertSee($edition->name);
    }

    // ----- Create -----

    public function test_admin_can_create_valid_edition(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.editions.store'), [
            'name' => 'RPPL 2030',
            'year' => 2030,
            'status' => 'upcoming',
        ]);

        $response->assertRedirect(route('admin.editions.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('editions', [
            'name' => 'RPPL 2030',
            'year' => 2030,
            'status' => 'upcoming',
        ]);
    }

    public function test_invalid_edition_data_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.editions.store'), [
            'name' => '',
            'year' => 'not-a-year',
            'status' => 'upcoming',
        ]);

        $response->assertSessionHasErrors(['name', 'year']);
        $this->assertDatabaseCount('editions', 0);
    }

    public function test_duplicate_year_is_rejected_on_create(): void
    {
        Edition::factory()->create(['year' => 2028]);

        $response = $this->actingAs($this->admin())->post(route('admin.editions.store'), [
            'name' => 'RPPL 2028 Redux',
            'year' => 2028,
            'status' => 'upcoming',
        ]);

        $response->assertSessionHasErrors('year');
        $this->assertDatabaseCount('editions', 1);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.editions.store'), [
            'name' => 'RPPL 2031',
            'year' => 2031,
            'status' => 'archived', // not one of the actual enum values
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('editions', 0);
    }

    // ----- Update -----

    public function test_admin_can_update_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);

        $response = $this->actingAs($this->admin())->put(route('admin.editions.update', $edition), [
            'name' => 'RPPL Updated',
            'year' => $edition->year,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.editions.index'));
        $this->assertDatabaseHas('editions', [
            'id' => $edition->id,
            'name' => 'RPPL Updated',
            'status' => 'active',
        ]);
    }

    public function test_edition_can_retain_its_own_year_when_updating(): void
    {
        $edition = Edition::factory()->create(['year' => 2032]);

        $response = $this->actingAs($this->admin())->put(route('admin.editions.update', $edition), [
            'name' => 'RPPL 2032 Renamed',
            'year' => 2032,
            'status' => $edition->status,
        ]);

        $response->assertSessionDoesntHaveErrors('year');
        $this->assertDatabaseHas('editions', ['id' => $edition->id, 'name' => 'RPPL 2032 Renamed']);
    }

    public function test_updating_to_a_conflicting_year_fails(): void
    {
        Edition::factory()->create(['year' => 2033]);
        $edition = Edition::factory()->create(['year' => 2034]);

        $response = $this->actingAs($this->admin())->put(route('admin.editions.update', $edition), [
            'name' => $edition->name,
            'year' => 2033,
            'status' => $edition->status,
        ]);

        $response->assertSessionHasErrors('year');
        $this->assertDatabaseHas('editions', ['id' => $edition->id, 'year' => 2034]);
    }

    public function test_completed_edition_cannot_be_moved_back_to_another_status(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);

        $response = $this->actingAs($this->admin())->put(route('admin.editions.update', $edition), [
            'name' => $edition->name,
            'year' => $edition->year,
            'status' => 'upcoming',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseHas('editions', ['id' => $edition->id, 'status' => 'completed']);
    }

    public function test_unauthorized_user_cannot_update_edition(): void
    {
        $edition = Edition::factory()->create();

        $this->put(route('admin.editions.update', $edition), [
            'name' => 'Nope', 'year' => $edition->year, 'status' => $edition->status,
        ])->assertRedirect(route('admin.login'));
    }

    // ----- Delete -----

    public function test_empty_edition_can_be_deleted(): void
    {
        $edition = Edition::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.editions.destroy', $edition));

        $response->assertRedirect(route('admin.editions.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('editions', ['id' => $edition->id]);
    }

    public function test_edition_with_tournament_data_cannot_be_deleted(): void
    {
        $edition = Edition::factory()->create();
        $team = Team::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $team->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.editions.destroy', $edition));

        $response->assertRedirect(route('admin.editions.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('editions', ['id' => $edition->id]);
    }

    public function test_edition_with_player_registrations_cannot_be_deleted(): void
    {
        $edition = Edition::factory()->create();
        PlayerRegistration::factory()->create(['edition_id' => $edition->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.editions.destroy', $edition))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('editions', ['id' => $edition->id]);
    }

    public function test_unauthorized_user_cannot_delete_edition(): void
    {
        $edition = Edition::factory()->create();

        $this->delete(route('admin.editions.destroy', $edition))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('editions', ['id' => $edition->id]);
    }

    // ----- Filtering -----

    public function test_search_filters_editions_by_name(): void
    {
        Edition::factory()->create(['name' => 'RPPL Alpha']);
        Edition::factory()->create(['name' => 'RPPL Beta']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.index', ['search' => 'Alpha']));

        $response->assertSee('RPPL Alpha')->assertDontSee('RPPL Beta');
    }

    public function test_status_filter_works(): void
    {
        Edition::factory()->create(['name' => 'RPPL Upcoming One', 'status' => 'upcoming']);
        Edition::factory()->create(['name' => 'RPPL Completed One', 'status' => 'completed']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.index', ['status' => 'completed']));

        $response->assertSee('RPPL Completed One')->assertDontSee('RPPL Upcoming One');
    }

    public function test_year_filter_works(): void
    {
        Edition::factory()->create(['name' => 'RPPL Year A', 'year' => 2040]);
        Edition::factory()->create(['name' => 'RPPL Year B', 'year' => 2041]);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.index', ['year' => 2040]));

        $response->assertSee('RPPL Year A')->assertDontSee('RPPL Year B');
    }

    public function test_pagination_preserves_query_parameters(): void
    {
        Edition::factory()->count(20)->create(['status' => 'active']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.index', ['status' => 'active', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('status=active', false);
    }

    // ----- UI / Routes -----

    public function test_editions_sidebar_link_is_visible_to_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.editions.index'), false);
    }

    public function test_scorer_does_not_see_edition_sidebar_link(): void
    {
        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.editions.index'), false);
    }

    public function test_named_edition_routes_resolve_correctly(): void
    {
        $edition = Edition::factory()->create();

        $this->assertSame('/admin/editions', route('admin.editions.index', [], false));
        $this->assertSame('/admin/editions/create', route('admin.editions.create', [], false));
        $this->assertSame("/admin/editions/{$edition->id}", route('admin.editions.show', $edition, false));
        $this->assertSame("/admin/editions/{$edition->id}/edit", route('admin.editions.edit', $edition, false));
    }
}
