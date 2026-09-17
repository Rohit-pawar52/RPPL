<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchPlayerManagementTest extends TestCase
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
     * A scheduled GameMatch with its two participating EditionTeams
     * under the same Edition, exactly as GameMatchFactory builds it.
     *
     * @return array{0: GameMatch, 1: EditionTeam, 2: EditionTeam}
     */
    private function matchWithTeams(array $matchAttributes = []): array
    {
        $match = GameMatch::factory()->create(array_merge(['match_status' => 'scheduled'], $matchAttributes));

        return [$match, $match->teamA, $match->teamB];
    }

    /**
     * A TeamPlayer belonging to the given EditionTeam, with an active
     * underlying Player.
     */
    private function teamPlayerFor(EditionTeam $editionTeam): TeamPlayer
    {
        return TeamPlayer::factory()->create(['edition_team_id' => $editionTeam->id]);
    }

    // ----- Authorization -----

    public function test_guest_is_blocked(): void
    {
        [$match] = $this->matchWithTeams();

        $this->get(route('admin.matches.players.index', $match))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_and_scorer_both_have_full_management_access(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $teamPlayer = $this->teamPlayerFor($teamA);

        foreach ([$this->admin(), $this->scorer()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.matches.players.index', $match))
                ->assertOk();

            $this->actingAs($user)
                ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
                ->assertRedirect(route('admin.matches.players.index', $match));

            $matchPlayer = MatchPlayer::where('match_id', $match->id)->where('team_player_id', $teamPlayer->id)->firstOrFail();

            $this->actingAs($user)
                ->patch(route('admin.matches.players.update', [$match, $matchPlayer]), ['designation' => 'captain'])
                ->assertRedirect(route('admin.matches.players.index', $match));

            $this->actingAs($user)
                ->delete(route('admin.matches.players.destroy', [$match, $matchPlayer]))
                ->assertRedirect(route('admin.matches.players.index', $match));
        }
    }

    // ----- Selection eligibility -----

    public function test_player_from_participating_team_can_be_selected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $teamPlayer = $this->teamPlayerFor($teamA);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertDatabaseHas('match_players', [
            'match_id' => $match->id,
            'team_player_id' => $teamPlayer->id,
        ]);
    }

    public function test_player_from_a_team_not_in_this_match_is_rejected(): void
    {
        [$match] = $this->matchWithTeams();
        $unrelatedTeamPlayer = TeamPlayer::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $unrelatedTeamPlayer->id])
            ->assertSessionHasErrors('team_player_id');

        $this->assertDatabaseMissing('match_players', ['team_player_id' => $unrelatedTeamPlayer->id]);
    }

    public function test_player_from_wrong_edition_is_rejected_even_if_team_names_collide(): void
    {
        [$match, $teamA] = $this->matchWithTeams();

        $otherEdition = Edition::factory()->create();
        $otherEditionTeam = EditionTeam::factory()->create([
            'edition_id' => $otherEdition->id,
            'team_id' => $teamA->team_id,
        ]);
        $teamPlayer = $this->teamPlayerFor($otherEditionTeam);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
            ->assertSessionHasErrors('team_player_id');
    }

    public function test_inactive_player_cannot_be_newly_selected(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $player = Player::factory()->inactive()->create();
        $registration = PlayerRegistration::factory()->create(['edition_id' => $match->edition_id, 'player_id' => $player->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $registration->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
            ->assertSessionHasErrors('team_player_id');
    }

    public function test_same_team_player_cannot_be_selected_twice(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $teamPlayer = $this->teamPlayerFor($teamA);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
            ->assertSessionHasErrors('team_player_id');
    }

    public function test_existing_selection_remains_visible_after_player_becomes_inactive(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $player = Player::factory()->create();
        $registration = PlayerRegistration::factory()->create(['edition_id' => $match->edition_id, 'player_id' => $player->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $registration->id]);
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $player->update(['is_active' => false]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.players.index', $match));

        $response->assertOk();
        $response->assertSee($player->name);
    }

    // ----- Captain / wicketkeeper, team-isolated -----

    public function test_assigning_captain_unsets_previous_captain_on_same_team_only(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $playerA1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id, 'is_captain' => true]);
        $playerA2 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $playerB1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamB)->id, 'is_captain' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.matches.players.update', [$match, $playerA2]), ['designation' => 'captain'])
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertFalse($playerA1->fresh()->is_captain);
        $this->assertTrue($playerA2->fresh()->is_captain);
        $this->assertTrue($playerB1->fresh()->is_captain, 'The other team\'s captain must be untouched.');
    }

    public function test_assigning_wicket_keeper_unsets_previous_wicket_keeper_on_same_team_only(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $playerA1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id, 'is_wicket_keeper' => true]);
        $playerA2 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $playerB1 = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamB)->id, 'is_wicket_keeper' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.matches.players.update', [$match, $playerA2]), ['designation' => 'wicket_keeper'])
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertFalse($playerA1->fresh()->is_wicket_keeper);
        $this->assertTrue($playerA2->fresh()->is_wicket_keeper);
        $this->assertTrue($playerB1->fresh()->is_wicket_keeper, 'The other team\'s wicketkeeper must be untouched.');
    }

    public function test_a_player_can_be_both_captain_and_wicket_keeper(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);

        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.matches.players.update', [$match, $matchPlayer]), ['designation' => 'captain']);
        $this->actingAs($admin)->patch(route('admin.matches.players.update', [$match, $matchPlayer]), ['designation' => 'wicket_keeper']);

        $matchPlayer->refresh();
        $this->assertTrue($matchPlayer->is_captain);
        $this->assertTrue($matchPlayer->is_wicket_keeper);
    }

    public function test_team_player_role_does_not_auto_assign_wicket_keeper_designation(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'role' => 'wicket_keeper']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id]);

        $matchPlayer = MatchPlayer::where('team_player_id', $teamPlayer->id)->firstOrFail();
        $this->assertFalse($matchPlayer->is_wicket_keeper);
    }

    // ----- Lifecycle lock -----

    public function test_playing_xi_is_editable_while_scheduled(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'scheduled']);
        $teamPlayer = $this->teamPlayerFor($teamA);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('match_players', ['team_player_id' => $teamPlayer->id]);
    }

    public function test_playing_xi_is_editable_while_toss(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'toss']);
        $teamPlayer = $this->teamPlayerFor($teamA);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id]);

        $this->assertDatabaseHas('match_players', ['team_player_id' => $teamPlayer->id]);
    }

    public function test_playing_xi_is_locked_once_live(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'live']);
        $teamPlayer = $this->teamPlayerFor($teamA);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id])
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertDatabaseMissing('match_players', ['team_player_id' => $teamPlayer->id]);
    }

    public function test_playing_xi_is_locked_once_completed(): void
    {
        [$match, $teamA] = $this->matchWithTeams(['match_status' => 'completed']);
        $teamPlayer = $this->teamPlayerFor($teamA);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id]);

        $this->assertDatabaseMissing('match_players', ['team_player_id' => $teamPlayer->id]);
    }

    public function test_playing_xi_is_locked_for_abandoned_and_cancelled_matches(): void
    {
        foreach (['abandoned', 'cancelled'] as $status) {
            [$match, $teamA] = $this->matchWithTeams(['match_status' => $status]);
            $teamPlayer = $this->teamPlayerFor($teamA);

            $this->actingAs($this->admin())
                ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id]);

            $this->assertDatabaseMissing('match_players', ['team_player_id' => $teamPlayer->id]);
        }
    }

    public function test_playing_xi_stays_locked_when_innings_exist_despite_stale_scheduled_status(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams(['match_status' => 'scheduled']);
        Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
        ]);
        $teamPlayer = $this->teamPlayerFor($teamA);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $match), ['team_player_id' => $teamPlayer->id]);

        $this->assertDatabaseMissing('match_players', ['team_player_id' => $teamPlayer->id]);
    }

    // ----- Removal / history protection -----

    public function test_unused_selection_can_be_removed(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $matchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.players.destroy', [$match, $matchPlayer]))
            ->assertRedirect(route('admin.matches.players.index', $match));

        $this->assertDatabaseMissing('match_players', ['id' => $matchPlayer->id]);
    }

    public function test_selection_referenced_by_scoring_history_cannot_be_removed(): void
    {
        [$match, $teamA, $teamB] = $this->matchWithTeams();
        $striker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $nonStriker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamA)->id]);
        $bowler = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $this->teamPlayerFor($teamB)->id]);

        $innings = Innings::factory()->create([
            'match_id' => $match->id,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
        ]);

        Delivery::create([
            'innings_id' => $innings->id,
            'delivery_sequence' => 1,
            'over_number' => 0,
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
        ]);

        // The match's own status is still 'scheduled' by default here —
        // this proves removal is blocked by scoring-history existing at
        // all, not merely by the lifecycle-lock status check.
        $this->actingAs($this->admin())
            ->delete(route('admin.matches.players.destroy', [$match, $striker]))
            ->assertRedirect(route('admin.matches.players.index', $match))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('match_players', ['id' => $striker->id]);
    }

    // ----- UI -----

    public function test_index_shows_selected_counts_and_no_contact_info(): void
    {
        [$match, $teamA] = $this->matchWithTeams();
        $player = Player::factory()->create(['phone' => '9998887776', 'email' => 'secret@example.com']);
        $registration = PlayerRegistration::factory()->create(['edition_id' => $match->edition_id, 'player_id' => $player->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $registration->id]);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.matches.players.index', $match));

        $response->assertOk();
        $response->assertSee('Selected: 1');
        $response->assertDontSee('9998887776');
        $response->assertDontSee('secret@example.com');
    }

    public function test_manage_playing_xi_link_visible_from_match_show_page(): void
    {
        [$match] = $this->matchWithTeams();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee(route('admin.matches.players.index', $match), false);
    }
}
