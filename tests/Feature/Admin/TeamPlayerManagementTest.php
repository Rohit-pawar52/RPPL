<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPlayerManagementTest extends TestCase
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

    /**
     * Builds a valid, same-edition EditionTeam + PlayerRegistration pair
     * ready to be assigned to a squad.
     *
     * @return array{0: EditionTeam, 1: PlayerRegistration}
     */
    private function eligiblePair(array $editionAttributes = [], array $teamAttributes = [], array $playerAttributes = []): array
    {
        $edition = Edition::factory()->create(array_merge(['status' => 'upcoming'], $editionAttributes));
        $team = Team::factory()->create(array_merge(['is_active' => true], $teamAttributes));
        $editionTeam = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $team->id]);

        $player = Player::factory()->create(array_merge(['is_active' => true], $playerAttributes));
        $registration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $player->id]);

        return [$editionTeam, $registration];
    }

    // ----- Authorization -----

    public function test_guest_is_blocked(): void
    {
        $this->get(route('admin.team-players.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_is_forbidden(): void
    {
        $scorer = $this->scorer();
        $teamPlayer = TeamPlayer::factory()->create();
        [$editionTeam, $registration] = $this->eligiblePair();

        $this->actingAs($scorer)->get(route('admin.team-players.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.team-players.create'))->assertForbidden();
        // Valid payload so validation passes and the controller's own
        // authorize() check is what actually gets exercised here.
        $this->actingAs($scorer)->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ])->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.team-players.show', $teamPlayer))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.team-players.edit', $teamPlayer))->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.team-players.update', $teamPlayer), ['jersey_number' => 9])->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.team-players.destroy', $teamPlayer))->assertForbidden();
    }

    public function test_admin_is_allowed(): void
    {
        $admin = $this->admin();
        $teamPlayer = TeamPlayer::factory()->create();

        $this->actingAs($admin)->get(route('admin.team-players.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.team-players.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.team-players.show', $teamPlayer))->assertOk();
        $this->actingAs($admin)->get(route('admin.team-players.edit', $teamPlayer))->assertOk();
    }

    // ----- Create -----

    public function test_valid_same_edition_assignment_succeeds(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair();

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
            'jersey_number' => 7,
            'role' => 'batter',
        ]);

        $response->assertRedirect(route('admin.team-players.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('team_players', [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
            'jersey_number' => 7,
        ]);
    }

    public function test_cross_edition_registration_is_rejected(): void
    {
        $editionA = Edition::factory()->create(['status' => 'upcoming']);
        $editionB = Edition::factory()->create(['status' => 'upcoming']);
        $editionTeam = EditionTeam::factory()->create(['edition_id' => $editionA->id]);
        // Registration belongs to a DIFFERENT edition than the team.
        $registration = PlayerRegistration::factory()->create(['edition_id' => $editionB->id]);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response->assertSessionHasErrors('player_registration_id');
        $this->assertDatabaseCount('team_players', 0);
    }

    public function test_inactive_player_is_rejected(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair(playerAttributes: ['is_active' => false]);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response->assertSessionHasErrors('player_registration_id');
        $this->assertDatabaseCount('team_players', 0);
    }

    public function test_inactive_team_is_rejected(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair(teamAttributes: ['is_active' => false]);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response->assertSessionHasErrors('edition_team_id');
        $this->assertDatabaseCount('team_players', 0);
    }

    public function test_completed_edition_is_rejected(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair(editionAttributes: ['status' => 'completed']);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response->assertSessionHasErrors('edition_team_id');
        $this->assertDatabaseCount('team_players', 0);
    }

    public function test_duplicate_squad_assignment_is_rejected(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair();
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
            'jersey_number' => 1,
        ]);

        $otherEditionTeam = EditionTeam::factory()->create(['edition_id' => $editionTeam->edition_id]);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $otherEditionTeam->id,
            'player_registration_id' => $registration->id,
        ]);

        $response->assertSessionHasErrors('player_registration_id');
        $this->assertDatabaseCount('team_players', 1);
    }

    public function test_different_players_can_join_the_same_edition_team(): void
    {
        [$editionTeam, $registrationOne] = $this->eligiblePair();
        $registrationTwo = PlayerRegistration::factory()->create(['edition_id' => $editionTeam->edition_id]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registrationOne->id,
            'jersey_number' => 1,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registrationTwo->id,
            'jersey_number' => 2,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('team_players', 2);
    }

    // ----- Jersey -----

    public function test_duplicate_jersey_within_same_edition_team_is_rejected(): void
    {
        [$editionTeam, $registrationOne] = $this->eligiblePair();
        $registrationTwo = PlayerRegistration::factory()->create(['edition_id' => $editionTeam->edition_id]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registrationOne->id,
            'jersey_number' => 10,
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registrationTwo->id,
            'jersey_number' => 10,
        ]);

        $response->assertSessionHasErrors('jersey_number');
        $this->assertDatabaseCount('team_players', 1);
    }

    public function test_same_jersey_across_different_edition_teams_is_allowed(): void
    {
        [$editionTeamA, $registrationA] = $this->eligiblePair();
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeamA->id,
            'player_registration_id' => $registrationA->id,
            'jersey_number' => 45,
        ]);

        [$editionTeamB, $registrationB] = $this->eligiblePair();

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeamB->id,
            'player_registration_id' => $registrationB->id,
            'jersey_number' => 45,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('team_players', 2);
    }

    // ----- Role -----

    public function test_valid_role_is_accepted(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair();

        $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
            'role' => 'wicket_keeper',
        ]);

        $this->assertDatabaseHas('team_players', [
            'player_registration_id' => $registration->id,
            'role' => 'wicket_keeper',
        ]);
    }

    public function test_invalid_role_is_rejected(): void
    {
        [$editionTeam, $registration] = $this->eligiblePair();

        $response = $this->actingAs($this->admin())->post(route('admin.team-players.store'), [
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
            'role' => 'captain', // not a real enum value
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseCount('team_players', 0);
    }

    // ----- Update -----

    public function test_admin_can_update_mutable_squad_metadata(): void
    {
        $teamPlayer = TeamPlayer::factory()->create(['jersey_number' => 5, 'role' => 'batter']);

        $response = $this->actingAs($this->admin())->put(route('admin.team-players.update', $teamPlayer), [
            'jersey_number' => 99,
            'role' => 'bowler',
        ]);

        $response->assertRedirect(route('admin.team-players.index'));
        $this->assertDatabaseHas('team_players', ['id' => $teamPlayer->id, 'jersey_number' => 99, 'role' => 'bowler']);
    }

    public function test_edition_team_id_cannot_be_changed_through_update(): void
    {
        $original = EditionTeam::factory()->create();
        $other = EditionTeam::factory()->create();
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $original->id]);

        $this->actingAs($this->admin())->put(route('admin.team-players.update', $teamPlayer), [
            'edition_team_id' => $other->id,
            'jersey_number' => 1,
        ]);

        $this->assertDatabaseHas('team_players', ['id' => $teamPlayer->id, 'edition_team_id' => $original->id]);
    }

    public function test_player_registration_id_cannot_be_changed_through_update(): void
    {
        $original = PlayerRegistration::factory()->create();
        $other = PlayerRegistration::factory()->create();
        $teamPlayer = TeamPlayer::factory()->create(['player_registration_id' => $original->id]);

        $this->actingAs($this->admin())->put(route('admin.team-players.update', $teamPlayer), [
            'player_registration_id' => $other->id,
            'jersey_number' => 1,
        ]);

        $this->assertDatabaseHas('team_players', ['id' => $teamPlayer->id, 'player_registration_id' => $original->id]);
    }

    // ----- History -----

    public function test_squad_record_remains_visible_after_player_becomes_inactive(): void
    {
        $player = Player::factory()->create(['name' => 'Later Inactive Player']);
        $registration = PlayerRegistration::factory()->create(['player_id' => $player->id]);
        $teamPlayer = TeamPlayer::factory()->create(['player_registration_id' => $registration->id]);
        $player->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.team-players.show', $teamPlayer))
            ->assertOk()
            ->assertSee('Later Inactive Player');
    }

    public function test_squad_record_remains_visible_after_team_becomes_inactive(): void
    {
        $team = Team::factory()->create(['name' => 'Later Inactive Team']);
        $editionTeam = EditionTeam::factory()->create(['team_id' => $team->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $editionTeam->id]);
        $team->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.team-players.show', $teamPlayer))
            ->assertOk()
            ->assertSee('Later Inactive Team');
    }

    public function test_squad_record_remains_visible_after_edition_becomes_completed(): void
    {
        $edition = Edition::factory()->create(['name' => 'Later Completed Edition', 'status' => 'upcoming']);
        $editionTeam = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $editionTeam->id]);
        $edition->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->get(route('admin.team-players.show', $teamPlayer))
            ->assertOk()
            ->assertSee('Later Completed Edition');
    }

    // ----- Delete -----

    public function test_unused_squad_assignment_can_be_deleted(): void
    {
        $teamPlayer = TeamPlayer::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.team-players.destroy', $teamPlayer));

        $response->assertRedirect(route('admin.team-players.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('team_players', ['id' => $teamPlayer->id]);
    }

    public function test_assignment_referenced_by_match_player_cannot_be_deleted(): void
    {
        $teamPlayer = TeamPlayer::factory()->create();
        $match = GameMatch::factory()->create();
        $matchPlayer = MatchPlayer::factory()->create(['team_player_id' => $teamPlayer->id, 'match_id' => $match->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.team-players.destroy', $teamPlayer));

        $response->assertRedirect(route('admin.team-players.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('team_players', ['id' => $teamPlayer->id]);
        $this->assertDatabaseHas('match_players', ['id' => $matchPlayer->id]);
    }

    public function test_unauthorized_delete_is_blocked(): void
    {
        $teamPlayer = TeamPlayer::factory()->create();

        $this->delete(route('admin.team-players.destroy', $teamPlayer))
            ->assertRedirect(route('admin.login'));

        $this->assertDatabaseHas('team_players', ['id' => $teamPlayer->id]);
    }

    // ----- Listing -----

    public function test_search_works(): void
    {
        $playerOne = Player::factory()->create(['name' => 'Searchable Alpha']);
        $playerTwo = Player::factory()->create(['name' => 'Searchable Beta']);
        TeamPlayer::factory()->create([
            'player_registration_id' => PlayerRegistration::factory()->create(['player_id' => $playerOne->id])->id,
        ]);
        TeamPlayer::factory()->create([
            'player_registration_id' => PlayerRegistration::factory()->create(['player_id' => $playerTwo->id])->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.team-players.index', ['search' => 'Alpha']));

        $response->assertSee('Searchable Alpha')->assertDontSee('Searchable Beta');
    }

    public function test_edition_team_filter_works(): void
    {
        $editionTeamOne = EditionTeam::factory()->create();
        $editionTeamTwo = EditionTeam::factory()->create();
        $playerOne = Player::factory()->create(['name' => 'Filtered In Player']);
        $playerTwo = Player::factory()->create(['name' => 'Filtered Out Player']);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeamOne->id,
            'player_registration_id' => PlayerRegistration::factory()->create(['player_id' => $playerOne->id])->id,
        ]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeamTwo->id,
            'player_registration_id' => PlayerRegistration::factory()->create(['player_id' => $playerTwo->id])->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.team-players.index', ['edition_team_id' => $editionTeamOne->id]));

        $response->assertSee('Filtered In Player')->assertDontSee('Filtered Out Player');
    }

    public function test_pagination_preserves_filters(): void
    {
        $editionTeam = EditionTeam::factory()->create();
        TeamPlayer::factory()->count(20)->create(['edition_team_id' => $editionTeam->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.team-players.index', ['edition_team_id' => $editionTeam->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('edition_team_id='.$editionTeam->id, false);
    }
}
