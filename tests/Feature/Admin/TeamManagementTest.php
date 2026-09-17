<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamManagementTest extends TestCase
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

    public function test_guest_cannot_access_teams(): void
    {
        $this->get(route('admin.teams.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_is_forbidden_from_team_management(): void
    {
        $scorer = $this->scorer();
        $team = Team::factory()->create();

        $this->actingAs($scorer)->get(route('admin.teams.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.teams.create'))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.teams.store'), ['name' => 'Hacker FC'])->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.teams.show', $team))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.teams.edit', $team))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.teams.update', $team), [
            'name' => 'Hacked', 'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.teams.destroy', $team))->assertForbidden();
    }

    public function test_admin_can_access_team_management(): void
    {
        $admin = $this->admin();
        $team = Team::factory()->create();

        $this->actingAs($admin)->get(route('admin.teams.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teams.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teams.show', $team))->assertOk();
        $this->actingAs($admin)->get(route('admin.teams.edit', $team))->assertOk();
    }

    // ----- Create -----

    public function test_admin_can_create_team(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.teams.store'), [
            'name' => 'Delhi Capitals',
            'short_name' => 'DC',
        ]);

        $response->assertRedirect(route('admin.teams.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('teams', ['name' => 'Delhi Capitals', 'short_name' => 'DC']);
    }

    public function test_new_team_defaults_active(): void
    {
        $this->actingAs($this->admin())->post(route('admin.teams.store'), ['name' => 'Default Active Team']);

        $team = Team::where('name', 'Default Active Team')->firstOrFail();

        $this->assertTrue($team->is_active);
    }

    public function test_team_validation_requires_name(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.teams.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_duplicate_team_name_is_rejected(): void
    {
        Team::factory()->create(['name' => 'Punjab Kings']);

        $response = $this->actingAs($this->admin())->post(route('admin.teams.store'), ['name' => 'Punjab Kings']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('teams', 1);
    }

    public function test_logo_upload_works_on_create(): void
    {
        Storage::fake('public');

        $logo = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->actingAs($this->admin())->post(route('admin.teams.store'), [
            'name' => 'Logo Team',
            'logo' => $logo,
        ]);

        $team = Team::where('name', 'Logo Team')->firstOrFail();

        $this->assertNotNull($team->logo_path);
        $this->assertStringStartsWith('teams/', $team->logo_path);
        Storage::disk('public')->assertExists($team->logo_path);
    }

    // ----- Update -----

    public function test_admin_can_update_team(): void
    {
        $team = Team::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($this->admin())->put(route('admin.teams.update', $team), [
            'name' => 'New Name',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.teams.index'));
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'New Name']);
    }

    public function test_admin_can_deactivate_team(): void
    {
        $team = Team::factory()->create();

        $this->actingAs($this->admin())->put(route('admin.teams.update', $team), [
            'name' => $team->name,
            'is_active' => 0,
        ]);

        $this->assertFalse($team->fresh()->is_active);
    }

    public function test_admin_can_reactivate_team(): void
    {
        $team = Team::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())->put(route('admin.teams.update', $team), [
            'name' => $team->name,
            'is_active' => 1,
        ]);

        $this->assertTrue($team->fresh()->is_active);
    }

    public function test_logo_replacement_cleans_old_logo(): void
    {
        Storage::fake('public');

        $team = Team::factory()->create(['logo_path' => null]);

        $firstLogo = UploadedFile::fake()->create('first.png', 100, 'image/png');
        $this->actingAs($this->admin())->put(route('admin.teams.update', $team), [
            'name' => $team->name,
            'is_active' => 1,
            'logo' => $firstLogo,
        ]);

        $team->refresh();
        $originalPath = $team->logo_path;
        $this->assertNotNull($originalPath);
        Storage::disk('public')->assertExists($originalPath);

        $secondLogo = UploadedFile::fake()->create('second.png', 100, 'image/png');
        $this->actingAs($this->admin())->put(route('admin.teams.update', $team), [
            'name' => $team->name,
            'is_active' => 1,
            'logo' => $secondLogo,
        ]);

        $team->refresh();

        $this->assertNotSame($originalPath, $team->logo_path);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($team->logo_path);
    }

    // ----- Lifecycle -----

    public function test_inactive_team_remains_visible_in_admin_index(): void
    {
        $team = Team::factory()->create(['is_active' => false, 'name' => 'Inactive Visible Team']);

        $this->actingAs($this->admin())
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertSee('Inactive Visible Team');
    }

    public function test_status_filter_works(): void
    {
        Team::factory()->create(['name' => 'Only Active Team']);
        Team::factory()->create(['name' => 'Only Inactive Team', 'is_active' => false]);

        $activeResponse = $this->actingAs($this->admin())->get(route('admin.teams.index', ['status' => 'active']));
        $activeResponse->assertSee('Only Active Team')->assertDontSee('Only Inactive Team');

        $inactiveResponse = $this->actingAs($this->admin())->get(route('admin.teams.index', ['status' => 'inactive']));
        $inactiveResponse->assertSee('Only Inactive Team')->assertDontSee('Only Active Team');
    }

    public function test_team_active_scope_excludes_inactive_teams(): void
    {
        Team::factory()->count(2)->create();
        Team::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(2, Team::active()->count());
    }

    public function test_plain_team_query_still_includes_inactive_teams(): void
    {
        Team::factory()->count(2)->create();
        Team::factory()->count(3)->create(['is_active' => false]);

        $this->assertSame(5, Team::query()->count());
    }

    public function test_deactivation_does_not_remove_edition_team_history(): void
    {
        $team = Team::factory()->create();
        $edition = Edition::factory()->create();
        EditionTeam::factory()->create(['team_id' => $team->id, 'edition_id' => $edition->id]);

        $this->actingAs($this->admin())->put(route('admin.teams.update', $team), [
            'name' => $team->name,
            'is_active' => 0,
        ]);

        $this->assertSame(1, $team->fresh()->editionTeams()->count());
    }

    // ----- Delete -----

    public function test_unused_team_can_be_deleted(): void
    {
        $team = Team::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.teams.destroy', $team));

        $response->assertRedirect(route('admin.teams.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_team_with_edition_history_cannot_be_deleted(): void
    {
        $team = Team::factory()->create();
        EditionTeam::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.teams.destroy', $team));

        $response->assertRedirect(route('admin.teams.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    public function test_blocked_deletion_preserves_team(): void
    {
        $team = Team::factory()->create(['name' => 'Protected Team']);
        EditionTeam::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->admin())->delete(route('admin.teams.destroy', $team));

        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Protected Team']);
    }

    public function test_blocked_deletion_preserves_logo(): void
    {
        Storage::fake('public');

        $team = Team::factory()->create(['logo_path' => 'teams/keep-me.png']);
        Storage::disk('public')->put('teams/keep-me.png', 'fake-contents');
        EditionTeam::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->admin())->delete(route('admin.teams.destroy', $team));

        Storage::disk('public')->assertExists('teams/keep-me.png');
    }

    public function test_successful_deletion_cleans_logo(): void
    {
        Storage::fake('public');

        $logo = UploadedFile::fake()->create('deletable.png', 100, 'image/png');
        $this->actingAs($this->admin())->post(route('admin.teams.store'), [
            'name' => 'Deletable Team',
            'logo' => $logo,
        ]);

        $team = Team::where('name', 'Deletable Team')->firstOrFail();
        $logoPath = $team->logo_path;
        Storage::disk('public')->assertExists($logoPath);

        $this->actingAs($this->admin())->delete(route('admin.teams.destroy', $team));

        Storage::disk('public')->assertMissing($logoPath);
        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
    }

    public function test_unauthorized_delete_is_blocked(): void
    {
        $team = Team::factory()->create();

        $this->delete(route('admin.teams.destroy', $team))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('teams', ['id' => $team->id]);
    }

    // ----- Listing -----

    public function test_search_works(): void
    {
        Team::factory()->create(['name' => 'Alpha Team']);
        Team::factory()->create(['name' => 'Beta Team']);

        $response = $this->actingAs($this->admin())->get(route('admin.teams.index', ['search' => 'Alpha']));

        $response->assertSee('Alpha Team')->assertDontSee('Beta Team');
    }

    public function test_participation_count_renders(): void
    {
        $team = Team::factory()->create();
        EditionTeam::factory()->count(3)->create(['team_id' => $team->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.teams.show', $team));

        $response->assertOk();
        $response->assertSee('3');
    }

    public function test_pagination_preserves_filters(): void
    {
        Team::factory()->count(20)->create(['is_active' => false]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.teams.index', ['status' => 'inactive', 'page' => 2]));

        $response->assertOk();
        $response->assertSee('status=inactive', false);
    }

    // ----- UI -----

    public function test_admin_sidebar_contains_teams_link(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertSee(route('admin.teams.index'), false);
    }

    public function test_scorer_sidebar_does_not_expose_team_management(): void
    {
        $this->actingAs($this->scorer())
            ->get(route('admin.dashboard'))
            ->assertDontSee(route('admin.teams.index'), false);
    }
}
