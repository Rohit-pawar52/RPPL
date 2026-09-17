<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EditionTeamManagementTest extends TestCase
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

    public function test_guest_is_blocked(): void
    {
        $this->get(route('admin.edition-teams.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_is_forbidden(): void
    {
        $scorer = $this->scorer();
        $editionTeam = EditionTeam::factory()->create();
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $team = Team::factory()->create();

        $this->actingAs($scorer)->get(route('admin.edition-teams.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.edition-teams.create'))->assertForbidden();
        // Valid payload (so validation passes and the controller's own
        // authorize() check is what actually gets exercised here).
        $this->actingAs($scorer)->post(route('admin.edition-teams.store'), [
            'edition_id' => $edition->id,
            'team_id' => $team->id,
        ])->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.edition-teams.show', $editionTeam))->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.edition-teams.destroy', $editionTeam))->assertForbidden();
    }

    public function test_admin_is_allowed(): void
    {
        $admin = $this->admin();
        $editionTeam = EditionTeam::factory()->create();

        $this->actingAs($admin)->get(route('admin.edition-teams.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.edition-teams.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.edition-teams.show', $editionTeam))->assertOk();
    }

    // ----- Create -----

    public function test_admin_can_add_active_team_to_eligible_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $team = Team::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.edition-teams.store'), [
            'edition_id' => $edition->id,
            'team_id' => $team->id,
        ]);

        $response->assertRedirect(route('admin.edition-teams.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('edition_teams', ['edition_id' => $edition->id, 'team_id' => $team->id]);
    }

    public function test_inactive_team_is_rejected(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $team = Team::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin())->post(route('admin.edition-teams.store'), [
            'edition_id' => $edition->id,
            'team_id' => $team->id,
        ]);

        $response->assertSessionHasErrors('team_id');
        $this->assertDatabaseCount('edition_teams', 0);
    }

    public function test_completed_edition_is_rejected(): void
    {
        $edition = Edition::factory()->create(['status' => 'completed']);
        $team = Team::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.edition-teams.store'), [
            'edition_id' => $edition->id,
            'team_id' => $team->id,
        ]);

        $response->assertSessionHasErrors('edition_id');
        $this->assertDatabaseCount('edition_teams', 0);
    }

    public function test_duplicate_edition_and_team_is_rejected(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $team = Team::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $team->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.edition-teams.store'), [
            'edition_id' => $edition->id,
            'team_id' => $team->id,
        ]);

        $response->assertSessionHasErrors('team_id');
        $this->assertDatabaseCount('edition_teams', 1);
    }

    public function test_same_team_can_participate_in_a_different_edition(): void
    {
        $editionOne = Edition::factory()->create(['status' => 'upcoming']);
        $editionTwo = Edition::factory()->create(['status' => 'upcoming']);
        $team = Team::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $editionOne->id, 'team_id' => $team->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.edition-teams.store'), [
            'edition_id' => $editionTwo->id,
            'team_id' => $team->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('edition_teams', 2);
    }

    public function test_different_teams_can_participate_in_the_same_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $teamOne = Team::factory()->create();
        $teamTwo = Team::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $teamOne->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.edition-teams.store'), [
            'edition_id' => $edition->id,
            'team_id' => $teamTwo->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('edition_teams', 2);
    }

    // ----- History preservation -----

    public function test_participation_remains_visible_after_team_becomes_inactive(): void
    {
        $team = Team::factory()->create(['name' => 'Later Inactive Team']);
        $editionTeam = EditionTeam::factory()->create(['team_id' => $team->id]);
        $team->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.edition-teams.show', $editionTeam))
            ->assertOk()
            ->assertSee('Later Inactive Team');
    }

    public function test_participation_remains_visible_after_edition_becomes_completed(): void
    {
        $edition = Edition::factory()->create(['name' => 'Later Completed Edition', 'status' => 'upcoming']);
        $editionTeam = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $edition->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->get(route('admin.edition-teams.show', $editionTeam))
            ->assertOk()
            ->assertSee('Later Completed Edition');
    }

    // ----- Delete -----

    public function test_unused_edition_team_can_be_removed(): void
    {
        $editionTeam = EditionTeam::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.edition-teams.destroy', $editionTeam));

        $response->assertRedirect(route('admin.edition-teams.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('edition_teams', ['id' => $editionTeam->id]);
    }

    public function test_edition_team_with_squad_usage_cannot_be_removed(): void
    {
        $editionTeam = EditionTeam::factory()->create();
        $registration = PlayerRegistration::factory()->create(['edition_id' => $editionTeam->edition_id]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.edition-teams.destroy', $editionTeam));

        $response->assertRedirect(route('admin.edition-teams.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('edition_teams', ['id' => $editionTeam->id]);
    }

    public function test_edition_team_referenced_by_a_match_cannot_be_removed(): void
    {
        $edition = Edition::factory()->create();
        $editionTeamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $editionTeamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $editionTeamA->id,
            'edition_team_b_id' => $editionTeamB->id,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.edition-teams.destroy', $editionTeamA));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('edition_teams', ['id' => $editionTeamA->id]);
    }

    public function test_edition_team_referenced_by_innings_cannot_be_removed(): void
    {
        $editionTeamBatting = EditionTeam::factory()->create();
        $editionTeamBowling = EditionTeam::factory()->create(['edition_id' => $editionTeamBatting->edition_id]);
        $match = GameMatch::factory()->create([
            'edition_id' => $editionTeamBatting->edition_id,
            'edition_team_a_id' => $editionTeamBatting->id,
            'edition_team_b_id' => $editionTeamBowling->id,
        ]);

        Innings::factory()->create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $editionTeamBatting->id,
            'bowling_team_id' => $editionTeamBowling->id,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.edition-teams.destroy', $editionTeamBatting));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('edition_teams', ['id' => $editionTeamBatting->id]);
    }

    public function test_blocked_deletion_leaves_historical_data_intact(): void
    {
        $editionTeam = EditionTeam::factory()->create();
        $registration = PlayerRegistration::factory()->create(['edition_id' => $editionTeam->edition_id]);
        $teamPlayer = TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $this->actingAs($this->admin())->delete(route('admin.edition-teams.destroy', $editionTeam));

        $this->assertDatabaseHas('edition_teams', ['id' => $editionTeam->id]);
        $this->assertDatabaseHas('team_players', ['id' => $teamPlayer->id]);
    }

    public function test_unauthorized_delete_is_blocked(): void
    {
        $editionTeam = EditionTeam::factory()->create();

        $this->delete(route('admin.edition-teams.destroy', $editionTeam))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('edition_teams', ['id' => $editionTeam->id]);
    }

    // ----- Listing -----

    public function test_listing_renders_with_eager_loaded_relations(): void
    {
        $edition = Edition::factory()->create(['name' => 'Listable Edition']);
        $team = Team::factory()->create(['name' => 'Listable Team']);
        EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $team->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-teams.index'));

        $response->assertOk()
            ->assertSee('Listable Edition')
            ->assertSee('Listable Team');
    }

    public function test_edition_filter_works(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $matchingTeam = Team::factory()->create(['name' => 'Filtered In Team']);
        $otherTeam = Team::factory()->create(['name' => 'Filtered Out Team']);
        EditionTeam::factory()->create(['edition_id' => $editionOne->id, 'team_id' => $matchingTeam->id]);
        EditionTeam::factory()->create(['edition_id' => $editionTwo->id, 'team_id' => $otherTeam->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-teams.index', ['edition_id' => $editionOne->id]));

        $response->assertSee('Filtered In Team')->assertDontSee('Filtered Out Team');
    }

    public function test_search_works(): void
    {
        EditionTeam::factory()->create(['team_id' => Team::factory()->create(['name' => 'Searchable Alpha'])->id]);
        EditionTeam::factory()->create(['team_id' => Team::factory()->create(['name' => 'Searchable Beta'])->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-teams.index', ['search' => 'Alpha']));

        $response->assertSee('Searchable Alpha')->assertDontSee('Searchable Beta');
    }

    public function test_pagination_preserves_filters(): void
    {
        $edition = Edition::factory()->create();
        EditionTeam::factory()->count(20)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-teams.index', ['edition_id' => $edition->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('edition_id='.$edition->id, false);
    }

    // ----- Immutability -----

    public function test_no_edit_or_update_route_exists(): void
    {
        $this->assertFalse(Route::has('admin.edition-teams.edit'));
        $this->assertFalse(Route::has('admin.edition-teams.update'));
    }
}
