<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameMatchManagementTest extends TestCase
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
     * Builds a valid, same-edition pair of active-team EditionTeams
     * ready to be scheduled against each other.
     *
     * @return array{0: Edition, 1: EditionTeam, 2: EditionTeam}
     */
    private function eligibleTeams(array $editionAttributes = []): array
    {
        $edition = Edition::factory()->create(array_merge(['status' => 'upcoming'], $editionAttributes));
        $teamA = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => true])->id,
        ]);
        $teamB = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => true])->id,
        ]);

        return [$edition, $teamA, $teamB];
    }

    private function validPayload(Edition $edition, EditionTeam $teamA, EditionTeam $teamB, array $overrides = []): array
    {
        return array_merge([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    // ----- Authorization -----

    public function test_guest_is_blocked(): void
    {
        $this->get(route('admin.matches.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_scorer_can_view_list_and_show(): void
    {
        $scorer = $this->scorer();
        $match = GameMatch::factory()->create();

        $this->actingAs($scorer)->get(route('admin.matches.index'))->assertOk();
        $this->actingAs($scorer)->get(route('admin.matches.show', $match))->assertOk();
    }

    public function test_scorer_cannot_create(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.store'), $this->validPayload($edition, $teamA, $teamB))
            ->assertForbidden();
    }

    public function test_scorer_cannot_update(): void
    {
        $match = GameMatch::factory()->create();

        // Valid payload (matching the match's own current identity) so
        // validation passes and the controller's own authorize() check
        // is what actually gets exercised here.
        $this->actingAs($this->scorer())
            ->put(route('admin.matches.update', $match), [
                'edition_id' => $match->edition_id,
                'edition_team_a_id' => $match->edition_team_a_id,
                'edition_team_b_id' => $match->edition_team_b_id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();
    }

    public function test_scorer_cannot_delete(): void
    {
        $match = GameMatch::factory()->create();

        $this->actingAs($this->scorer())
            ->delete(route('admin.matches.destroy', $match))
            ->assertForbidden();
    }

    public function test_admin_has_full_management_access(): void
    {
        $admin = $this->admin();
        $match = GameMatch::factory()->create();

        $this->actingAs($admin)->get(route('admin.matches.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.matches.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.matches.show', $match))->assertOk();
        $this->actingAs($admin)->get(route('admin.matches.edit', $match))->assertOk();
    }

    // ----- Create -----

    public function test_valid_fixture_succeeds(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $teamB, ['match_number' => 1])
        );

        $response->assertRedirect(route('admin.matches.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('matches', [
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_number' => 1,
        ]);
    }

    public function test_completed_edition_is_rejected(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams(['status' => 'completed']);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $teamB)
        );

        $response->assertSessionHasErrors('edition_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_team_a_equal_to_team_b_is_rejected(): void
    {
        [$edition, $teamA] = $this->eligibleTeams();

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $teamA)
        );

        $response->assertSessionHasErrors('edition_team_a_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_team_a_from_wrong_edition_is_rejected(): void
    {
        [$edition, , $teamB] = $this->eligibleTeams();
        $otherEdition = Edition::factory()->create(['status' => 'upcoming']);
        $wrongTeamA = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $wrongTeamA, $teamB)
        );

        $response->assertSessionHasErrors('edition_team_a_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_team_b_from_wrong_edition_is_rejected(): void
    {
        [$edition, $teamA] = $this->eligibleTeams();
        $otherEdition = Edition::factory()->create(['status' => 'upcoming']);
        $wrongTeamB = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $wrongTeamB)
        );

        $response->assertSessionHasErrors('edition_team_b_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_inactive_underlying_team_is_rejected(): void
    {
        $edition = Edition::factory()->create(['status' => 'upcoming']);
        $teamA = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => false])->id,
        ]);
        $teamB = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => true])->id,
        ]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $teamB)
        );

        $response->assertSessionHasErrors('edition_team_a_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_inactive_venue_is_rejected(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();
        $venue = Venue::factory()->create(['is_active' => false]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $teamB, ['venue_id' => $venue->id])
        );

        $response->assertSessionHasErrors('venue_id');
        $this->assertDatabaseCount('matches', 0);
    }

    public function test_nullable_venue_is_accepted(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $teamB)
        );

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('matches', ['edition_id' => $edition->id, 'venue_id' => null]);
    }

    public function test_duplicate_match_number_in_same_edition_is_rejected(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();
        GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 1]);

        $thirdTeam = EditionTeam::factory()->create([
            'edition_id' => $edition->id,
            'team_id' => Team::factory()->create(['is_active' => true])->id,
        ]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($edition, $teamA, $thirdTeam, ['match_number' => 1])
        );

        $response->assertSessionHasErrors('match_number');
        $this->assertDatabaseCount('matches', 1);
    }

    public function test_same_match_number_in_different_edition_is_allowed(): void
    {
        [$editionOne, $teamA, $teamB] = $this->eligibleTeams();
        GameMatch::factory()->create(['edition_id' => $editionOne->id, 'match_number' => 1]);

        [$editionTwo, $teamC, $teamD] = $this->eligibleTeams();

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.store'),
            $this->validPayload($editionTwo, $teamC, $teamD, ['match_number' => 1])
        );

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('matches', 2);
    }

    // ----- Update -----

    public function test_unstarted_fixture_can_have_scheduling_fields_changed(): void
    {
        $match = GameMatch::factory()->create(['scheduled_at' => now()->addDays(3)]);
        $newVenue = Venue::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin())->put(route('admin.matches.update', $match), [
            'edition_id' => $match->edition_id,
            'edition_team_a_id' => $match->edition_team_a_id,
            'edition_team_b_id' => $match->edition_team_b_id,
            'venue_id' => $newVenue->id,
            'scheduled_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('admin.matches.index'));
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'venue_id' => $newVenue->id]);
    }

    /**
     * Deterministic reproduction of an edge case discovered via this
     * suite: an unstarted match's edition can be marked completed
     * before the match happens. Editing an unrelated field (venue) on
     * that still-unstarted fixture must not fail merely because the
     * unchanged edition_id/team ids now belong to ineligible-for-NEW
     * selection (completed edition / inactive team) records.
     */
    public function test_unstarted_fixture_survives_edit_after_its_own_edition_completes(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
        ]);
        $edition->update(['status' => 'completed']);
        $newVenue = Venue::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin())->put(route('admin.matches.update', $match), [
            'edition_id' => $match->edition_id,
            'edition_team_a_id' => $match->edition_team_a_id,
            'edition_team_b_id' => $match->edition_team_b_id,
            'venue_id' => $newVenue->id,
            'scheduled_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'venue_id' => $newVenue->id]);
    }

    public function test_team_edition_consistency_still_enforced_on_update(): void
    {
        $match = GameMatch::factory()->create();
        $otherEdition = Edition::factory()->create(['status' => 'upcoming']);
        $wrongTeam = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);

        $response = $this->actingAs($this->admin())->put(route('admin.matches.update', $match), [
            'edition_id' => $match->edition_id,
            'edition_team_a_id' => $wrongTeam->id,
            'edition_team_b_id' => $match->edition_team_b_id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('edition_team_a_id');
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'edition_team_a_id' => $match->edition_team_a_id]);
    }

    public function test_duplicate_match_number_still_rejected_on_update(): void
    {
        [$edition, $teamA, $teamB] = $this->eligibleTeams();
        GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 1]);
        $match = GameMatch::factory()->create(['edition_id' => $edition->id, 'match_number' => 2, 'edition_team_a_id' => $teamA->id, 'edition_team_b_id' => $teamB->id]);

        $response = $this->actingAs($this->admin())->put(route('admin.matches.update', $match), [
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_number' => 1,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('match_number');
        $this->assertDatabaseHas('matches', ['id' => $match->id, 'match_number' => 2]);
    }

    public function test_identity_cannot_change_once_match_player_exists(): void
    {
        $match = GameMatch::factory()->create();
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id]);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $otherEdition = Edition::factory()->create(['status' => 'upcoming']);
        $otherTeam = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);

        $response = $this->actingAs($this->admin())->put(route('admin.matches.update', $match), [
            'edition_id' => $otherEdition->id,
            'edition_team_a_id' => $otherTeam->id,
            'edition_team_b_id' => $match->edition_team_b_id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('admin.matches.index'));
        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'edition_id' => $match->edition_id,
            'edition_team_a_id' => $match->edition_team_a_id,
        ]);
    }

    public function test_identity_cannot_change_once_innings_exists(): void
    {
        $match = GameMatch::factory()->create();
        Innings::factory()->create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
        ]);

        $otherEdition = Edition::factory()->create(['status' => 'upcoming']);
        $otherTeam = EditionTeam::factory()->create(['edition_id' => $otherEdition->id]);

        $this->actingAs($this->admin())->put(route('admin.matches.update', $match), [
            'edition_id' => $otherEdition->id,
            'edition_team_a_id' => $otherTeam->id,
            'edition_team_b_id' => $match->edition_team_b_id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'edition_id' => $match->edition_id,
            'edition_team_a_id' => $match->edition_team_a_id,
        ]);
    }

    // ----- History -----

    public function test_match_remains_visible_after_team_becomes_inactive(): void
    {
        $team = Team::factory()->create(['name' => 'Later Inactive Match Team', 'is_active' => true]);
        $editionTeam = EditionTeam::factory()->create(['team_id' => $team->id]);
        $match = GameMatch::factory()->create(['edition_team_a_id' => $editionTeam->id, 'edition_id' => $editionTeam->edition_id]);
        $team->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.matches.show', $match))
            ->assertOk()
            ->assertSee('Later Inactive Match Team');
    }

    public function test_match_remains_visible_after_venue_becomes_inactive(): void
    {
        $venue = Venue::factory()->create(['name' => 'Later Inactive Match Venue', 'is_active' => true]);
        $match = GameMatch::factory()->create(['venue_id' => $venue->id]);
        $venue->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.matches.show', $match))
            ->assertOk()
            ->assertSee('Later Inactive Match Venue');
    }

    public function test_match_remains_visible_after_edition_becomes_completed(): void
    {
        $edition = Edition::factory()->create(['name' => 'Later Completed Match Edition', 'status' => 'upcoming']);
        $editionTeamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $editionTeamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $editionTeamA->id,
            'edition_team_b_id' => $editionTeamB->id,
        ]);
        $edition->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->get(route('admin.matches.show', $match))
            ->assertOk()
            ->assertSee('Later Completed Match Edition');
    }

    // ----- Delete -----

    public function test_unused_fixture_can_be_deleted(): void
    {
        $match = GameMatch::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.matches.destroy', $match));

        $response->assertRedirect(route('admin.matches.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('matches', ['id' => $match->id]);
    }

    public function test_match_with_match_player_cannot_be_deleted(): void
    {
        $match = GameMatch::factory()->create();
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id]);
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $response = $this->actingAs($this->admin())->delete(route('admin.matches.destroy', $match));

        $response->assertRedirect(route('admin.matches.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('matches', ['id' => $match->id]);
        $this->assertDatabaseHas('match_players', ['id' => $matchPlayer->id]);
    }

    public function test_match_with_innings_cannot_be_deleted(): void
    {
        $match = GameMatch::factory()->create();
        $innings = Innings::factory()->create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
        ]);

        $response = $this->actingAs($this->admin())->delete(route('admin.matches.destroy', $match));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('matches', ['id' => $match->id]);
        $this->assertDatabaseHas('innings', ['id' => $innings->id]);
    }

    // ----- Listing -----

    public function test_edition_filter_works(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $teamOne = EditionTeam::factory()->create(['edition_id' => $editionOne->id, 'team_id' => Team::factory()->create(['name' => 'Filtered In Match Team'])->id]);
        $teamTwo = EditionTeam::factory()->create(['edition_id' => $editionTwo->id, 'team_id' => Team::factory()->create(['name' => 'Filtered Out Match Team'])->id]);
        GameMatch::factory()->create(['edition_id' => $editionOne->id, 'edition_team_a_id' => $teamOne->id]);
        GameMatch::factory()->create(['edition_id' => $editionTwo->id, 'edition_team_a_id' => $teamTwo->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['edition_id' => $editionOne->id]));

        $response->assertSee('Filtered In Match Team')->assertDontSee('Filtered Out Match Team');
    }

    public function test_status_filter_works(): void
    {
        $teamLive = Team::factory()->create(['name' => 'Live Status Team']);
        $teamScheduled = Team::factory()->create(['name' => 'Scheduled Status Team']);
        GameMatch::factory()->create([
            'match_status' => 'live',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $teamLive->id]),
        ]);
        GameMatch::factory()->create([
            'match_status' => 'scheduled',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $teamScheduled->id]),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['match_status' => 'live']));

        $response->assertSee('Live Status Team')->assertDontSee('Scheduled Status Team');
    }

    public function test_search_works(): void
    {
        $teamOne = Team::factory()->create(['name' => 'Searchable Match Alpha']);
        $teamTwo = Team::factory()->create(['name' => 'Searchable Match Beta']);
        GameMatch::factory()->create(['edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $teamOne->id])]);
        GameMatch::factory()->create(['edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $teamTwo->id])]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['search' => 'Alpha']));

        $response->assertSee('Searchable Match Alpha')->assertDontSee('Searchable Match Beta');
    }

    public function test_pagination_preserves_filters(): void
    {
        $edition = Edition::factory()->create();
        GameMatch::factory()->count(20)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['edition_id' => $edition->id, 'page' => 2]));

        $response->assertOk();
        $response->assertSee('edition_id='.$edition->id, false);
    }

    // ----- Date range -----

    public function test_date_range_filters_by_scheduled_at(): void
    {
        $inRangeTeam = Team::factory()->create(['name' => 'In Range Match Team']);
        $beforeTeam = Team::factory()->create(['name' => 'Before Range Match Team']);
        $afterTeam = Team::factory()->create(['name' => 'After Range Match Team']);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-03-15 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $inRangeTeam->id]),
        ]);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-01-01 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $beforeTeam->id]),
        ]);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-06-01 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $afterTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.index', [
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
        ]));

        $response->assertSee('In Range Match Team')
            ->assertDontSee('Before Range Match Team')
            ->assertDontSee('After Range Match Team');
    }

    public function test_from_date_only_filters_open_ended(): void
    {
        $recentTeam = Team::factory()->create(['name' => 'Recent Match Team']);
        $oldTeam = Team::factory()->create(['name' => 'Old Match Team']);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-06-01 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $recentTeam->id]),
        ]);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-01-01 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $oldTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['from_date' => '2026-05-01']));

        $response->assertSee('Recent Match Team')->assertDontSee('Old Match Team');
    }

    public function test_to_date_only_filters_open_started(): void
    {
        $oldTeam = Team::factory()->create(['name' => 'Old To-Date Match Team']);
        $recentTeam = Team::factory()->create(['name' => 'Recent To-Date Match Team']);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-01-01 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $oldTeam->id]),
        ]);
        GameMatch::factory()->create([
            'scheduled_at' => '2026-06-01 10:00:00',
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $recentTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['to_date' => '2026-02-01']));

        $response->assertSee('Old To-Date Match Team')->assertDontSee('Recent To-Date Match Team');
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.matches.index', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors('to_date');
    }

    // ----- Sorting -----

    public function test_sorting_ascending_by_scheduled_at(): void
    {
        $earlyTeam = Team::factory()->create(['name' => 'Early Sort Match Team']);
        $lateTeam = Team::factory()->create(['name' => 'Late Sort Match Team']);
        GameMatch::factory()->create([
            'scheduled_at' => now()->addDays(10),
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $lateTeam->id]),
        ]);
        GameMatch::factory()->create([
            'scheduled_at' => now()->addDays(1),
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $earlyTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.index', [
            'sort' => 'scheduled_at', 'direction' => 'asc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, 'Late Sort Match Team'),
            strpos($body, 'Early Sort Match Team')
        );
    }

    public function test_sorting_descending_by_scheduled_at(): void
    {
        $earlyTeam = Team::factory()->create(['name' => 'Early Desc Match Team']);
        $lateTeam = Team::factory()->create(['name' => 'Late Desc Match Team']);
        GameMatch::factory()->create([
            'scheduled_at' => now()->addDays(10),
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $lateTeam->id]),
        ]);
        GameMatch::factory()->create([
            'scheduled_at' => now()->addDays(1),
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $earlyTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.index', [
            'sort' => 'scheduled_at', 'direction' => 'desc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, 'Early Desc Match Team'),
            strpos($body, 'Late Desc Match Team')
        );
    }

    public function test_invalid_sort_column_falls_back_to_default_safely(): void
    {
        GameMatch::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.index', ['sort' => 'password']));

        $response->assertOk();
    }

    // ----- Export -----

    public function test_export_contains_only_filtered_matches(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $includedTeam = Team::factory()->create(['name' => 'Exported Match Team']);
        $excludedTeam = Team::factory()->create(['name' => 'Not Exported Match Team']);
        GameMatch::factory()->create([
            'edition_id' => $editionOne->id,
            'edition_team_a_id' => EditionTeam::factory()->create(['edition_id' => $editionOne->id, 'team_id' => $includedTeam->id]),
        ]);
        GameMatch::factory()->create([
            'edition_id' => $editionTwo->id,
            'edition_team_a_id' => EditionTeam::factory()->create(['edition_id' => $editionTwo->id, 'team_id' => $excludedTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.matches.export', ['edition_id' => $editionOne->id]));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Exported Match Team', $content);
        $this->assertStringNotContainsString('Not Exported Match Team', $content);
    }

    // ----- Selected-rows export -----

    public function test_selected_export_contains_only_the_selected_matches(): void
    {
        $selectedTeam = Team::factory()->create(['name' => 'Selected Export Match Team']);
        $notSelectedTeam = Team::factory()->create(['name' => 'Unselected Export Match Team']);
        $selected = GameMatch::factory()->create([
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $selectedTeam->id]),
        ]);
        GameMatch::factory()->create([
            'edition_team_a_id' => EditionTeam::factory()->create(['team_id' => $notSelectedTeam->id]),
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.matches.export-selected'), [
            'selected_ids' => [$selected->id],
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Selected Export Match Team', $content);
        $this->assertStringNotContainsString('Unselected Export Match Team', $content);
    }

    public function test_selected_export_ignores_ambient_filters(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $team = Team::factory()->create(['name' => 'Ambient Filter Ignored Match Team']);
        $selected = GameMatch::factory()->create([
            'edition_id' => $editionTwo->id,
            'edition_team_a_id' => EditionTeam::factory()->create(['edition_id' => $editionTwo->id, 'team_id' => $team->id]),
        ]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.matches.export-selected', ['edition_id' => $editionOne->id]),
            ['selected_ids' => [$selected->id]]
        );

        $response->assertOk();
        $this->assertStringContainsString('Ambient Filter Ignored Match Team', $response->streamedContent());
    }

    public function test_selected_export_rejects_a_nonexistent_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.matches.export-selected'), [
            'selected_ids' => [999999],
        ]);

        $response->assertSessionHasErrors('selected_ids.0');
    }

    public function test_selected_export_requires_at_least_one_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.matches.export-selected'), [
            'selected_ids' => [],
        ]);

        $response->assertSessionHasErrors('selected_ids');
    }

    /**
     * Unlike PlayerRegistration, GameMatchPolicy::viewAny() deliberately
     * allows both admin and scorer (scorers need to look up fixtures for
     * scoring) — see the policy's own docblock. export()/exportSelected()
     * reuse that identical viewAny gate, so a scorer is NOT forbidden
     * here; only a guest (unauthenticated) is.
     */
    public function test_scorer_can_use_export_and_selected_export(): void
    {
        $scorer = $this->scorer();
        $match = GameMatch::factory()->create();

        $this->actingAs($scorer)->get(route('admin.matches.export'))->assertOk();
        $this->actingAs($scorer)
            ->post(route('admin.matches.export-selected'), ['selected_ids' => [$match->id]])
            ->assertOk();
    }

    public function test_guest_cannot_export(): void
    {
        $this->get(route('admin.matches.export'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_guest_cannot_use_selected_export(): void
    {
        $match = GameMatch::factory()->create();

        $this->post(route('admin.matches.export-selected'), ['selected_ids' => [$match->id]])
            ->assertRedirect(route('admin.login'));
    }
}
