<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_shows_active_teams_and_hides_inactive_ones(): void
    {
        $active = Team::factory()->create(['name' => 'Mumbai Marauders', 'is_active' => true]);
        $inactive = Team::factory()->create(['name' => 'Retired Rhinos', 'is_active' => false]);

        $response = $this->get(route('public.teams.index'));

        $response->assertOk();
        $response->assertSee($active->name);
        $response->assertDontSee($inactive->name);
    }

    public function test_search_filters_the_directory(): void
    {
        Team::factory()->create(['name' => 'Chennai Chargers', 'is_active' => true]);
        Team::factory()->create(['name' => 'Delhi Dynamos', 'is_active' => true]);

        $response = $this->get(route('public.teams.index', ['search' => 'Chennai']));

        $response->assertOk();
        $response->assertSee('Chennai Chargers');
        $response->assertDontSee('Delhi Dynamos');
    }

    public function test_inactive_teams_historical_profile_remains_accessible(): void
    {
        $team = Team::factory()->create(['is_active' => false]);
        $edition = Edition::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $team->id]);

        $response = $this->get(route('public.teams.show', $team));

        $response->assertOk();
        $response->assertSee($team->name);
    }

    public function test_squad_renders_including_inactive_historical_player_and_hides_private_fields(): void
    {
        $team = Team::factory()->create();
        $opponent = Team::factory()->create();
        $edition = Edition::factory()->create();
        $editionTeam = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $team->id]);
        $opponentEditionTeam = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => $opponent->id]);

        $player = Player::factory()->create([
            'name' => 'Retired Regular',
            'is_active' => false,
            'phone' => '9991112222',
            'email' => 'private@example.com',
        ]);
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'registration_fee' => 3000.00,
            'payment_status' => 'paid',
        ]);
        TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
            'jersey_number' => 7,
            'role' => 'batter',
        ]);

        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $editionTeam->id,
            'edition_team_b_id' => $opponentEditionTeam->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $editionTeam->id,
            'match_result' => $team->name.' won by 5 runs',
        ]);

        $response = $this->get(route('public.teams.show', ['team' => $team, 'edition_id' => $edition->id]));

        $response->assertOk();
        // Inactive player still appears in the historical squad.
        $response->assertSee('Retired Regular');
        $response->assertSee('#7');
        // Recent match, opponent, and stored result render with a link.
        $response->assertSee('vs '.$opponent->name);
        $response->assertSee($team->name.' won by 5 runs');
        $response->assertSee(route('public.matches.show', $match), false);
        $response->assertSee(route('public.players.show', $player), false);

        $response->assertDontSee($player->phone);
        $response->assertDontSee($player->email);
        $response->assertDontSee('3000.00');
        $response->assertDontSee('paid');
    }

    public function test_edition_filter_ignores_unrelated_edition_and_falls_back_to_default(): void
    {
        $team = Team::factory()->create();
        $ownEdition = Edition::factory()->create(['status' => 'active']);
        $unrelatedEdition = Edition::factory()->create();
        EditionTeam::factory()->create(['edition_id' => $ownEdition->id, 'team_id' => $team->id]);

        $response = $this->get(route('public.teams.show', ['team' => $team, 'edition_id' => $unrelatedEdition->id]));

        $response->assertOk();
        // Falls back to the team's own (only) participation rather than
        // trusting the unrelated edition_id.
        $response->assertSee($ownEdition->name);
    }

    public function test_team_with_no_participation_shows_empty_states_without_failing(): void
    {
        $team = Team::factory()->create();

        $response = $this->get(route('public.teams.show', $team));

        $response->assertOk();
        $response->assertSee('No tournament history available yet.');
    }
}
