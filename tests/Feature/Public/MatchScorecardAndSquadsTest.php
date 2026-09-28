<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchScorecardAndSquadsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A completed match with a full Playing XI on both sides, a scored
     * innings containing a wicket (so bowling figures and fall of
     * wickets both have something to assert on), and one bench player
     * per team who is on the squad but never selected for the XI.
     *
     * @return array{0: GameMatch, 1: Player, 2: Player, 3: Player, 4: Player}
     *                                                                         match, striker, bowler, benchPlayerTeamA, benchPlayerTeamB
     */
    private function matchWithFullScorecard(): array
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'live',
            'started_at' => now(),
        ]);
        $match->update(['toss_winner_team_id' => $teamA->id, 'toss_decision' => 'bat']);

        $striker = Player::factory()->create();
        $strikerTeamPlayer = TeamPlayer::factory()->create([
            'edition_team_id' => $teamA->id,
            'player_registration_id' => PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $striker->id])->id,
            'jersey_number' => 7,
            'role' => 'batter',
        ]);
        $strikerMatchPlayer = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => $strikerTeamPlayer->id,
            'is_captain' => true,
        ]);

        $nonStrikerTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'jersey_number' => 9, 'role' => 'wicket_keeper']);
        $nonStriker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => $nonStrikerTeamPlayer->id,
            'is_wicket_keeper' => true,
        ]);

        // On Team A's squad, but never given a MatchPlayer row for this
        // match — must never appear on the public Squads page. jersey
        // numbers throughout this fixture are all explicit and distinct
        // per team (never left to the factory's random default), since
        // team_players has a real (edition_team_id, jersey_number)
        // unique constraint the factory's fake()->unique() knows nothing
        // about an explicitly-passed override elsewhere in the same team.
        $benchPlayerA = Player::factory()->create();
        TeamPlayer::factory()->create([
            'edition_team_id' => $teamA->id,
            'player_registration_id' => PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $benchPlayerA->id])->id,
            'jersey_number' => 11,
        ]);

        $bowler = Player::factory()->create();
        $bowlerTeamPlayer = TeamPlayer::factory()->create([
            'edition_team_id' => $teamB->id,
            'player_registration_id' => PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $bowler->id])->id,
            'jersey_number' => 21,
            'role' => 'bowler',
        ]);
        $bowlerMatchPlayer = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $bowlerTeamPlayer->id]);

        $benchPlayerB = Player::factory()->create();
        TeamPlayer::factory()->create([
            'edition_team_id' => $teamB->id,
            'player_registration_id' => PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $benchPlayerB->id])->id,
            'jersey_number' => 23,
        ]);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ]);

        $deliveries = app(DeliveryService::class);

        $deliveries->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $strikerMatchPlayer->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowlerMatchPlayer->id,
            'runs_off_bat' => 4,
        ]);

        $deliveries->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $strikerMatchPlayer->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowlerMatchPlayer->id,
            'runs_off_bat' => 0,
            'is_wicket' => true,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $strikerMatchPlayer->id,
        ]);

        $match->update(['match_status' => 'completed', 'match_result' => $teamA->team->name.' won by 5 wickets', 'winner_team_id' => $teamA->id, 'result_type' => 'won']);
        $innings->update(['status' => 'completed']);

        return [$match->fresh(), $striker, $bowler, $benchPlayerA, $benchPlayerB];
    }

    // ----- Scorecard structure -----

    public function test_scorecard_renders_batting_extras_total_fow_and_bowling(): void
    {
        [$match, $striker, $bowler] = $this->matchWithFullScorecard();

        $response = $this->get(route('public.matches.scorecard', $match));

        $response->assertOk();
        // Batting
        $response->assertSee($striker->name);
        $response->assertSee('b '.$bowler->name); // dismissal text
        // Extras / Total
        $response->assertSee('Extras:');
        $response->assertSee('Total:');
        // Fall of wickets
        $response->assertSee('Fall of wickets:');
        $response->assertSee('1-4'); // wicket fell at cumulative score 4
        // Bowling
        $response->assertSee($bowler->name);
        $response->assertSee('Econ');
    }

    public function test_scorecard_with_two_innings_renders_a_pure_css_toggle_showing_one_at_a_time(): void
    {
        [$match] = $this->matchWithFullScorecard();
        $teamB = $match->teamB;

        Innings::create([
            'match_id' => $match->id,
            'innings_number' => 2,
            'batting_team_id' => $teamB->id,
            'bowling_team_id' => $match->teamA->id,
            'status' => 'completed',
            'total_runs' => 10,
            'total_wickets' => 1,
        ]);

        $response = $this->get(route('public.matches.scorecard', $match));

        $response->assertOk();
        // Two radio-driven pills, first innings selected by default —
        // no JS switcher, both panels exist in the DOM but only one is
        // visible at a time via the has-checked/group-has-[] CSS.
        $response->assertSee('name="innings-tab"', false);
        $response->assertSee('value="1"', false);
        $response->assertSee('value="2"', false);
        $response->assertSee("group-has-[input[value='1']:checked]/innings:block", false);
        $response->assertSee("group-has-[input[value='2']:checked]/innings:block", false);
    }

    public function test_scorecard_shows_prominent_result_and_hides_pdf_cta(): void
    {
        [$match] = $this->matchWithFullScorecard();

        $response = $this->get(route('public.matches.scorecard', $match));

        $response->assertOk();
        $response->assertSee($match->match_result);
        $response->assertDontSee('Download PDF');
    }

    // ----- Tabs / labels -----

    public function test_match_tabs_show_live_scorecard_squads_and_match_info_in_order(): void
    {
        [$match] = $this->matchWithFullScorecard();

        $response = $this->get(route('public.matches.show', $match));

        $response->assertOk();
        $response->assertSeeInOrder(['Live', 'Scorecard', 'Squads', 'Match Info']);
        $response->assertDontSee('Ball-by-Ball');
    }

    public function test_scheduled_match_with_no_innings_shows_only_squads_and_match_info_tabs(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'scheduled',
        ]);

        $response = $this->get(route('public.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Squads');
        $response->assertSee('Match Info');
        $response->assertDontSee('Scorecard');
    }

    // ----- Squads page -----

    public function test_squads_page_shows_both_playing_xis_with_captain_and_wicketkeeper_markers(): void
    {
        [$match, $striker, $bowler, $benchPlayerA, $benchPlayerB] = $this->matchWithFullScorecard();

        $response = $this->get(route('public.matches.squads', $match));

        $response->assertOk();
        $response->assertSee($striker->name); // captain
        $response->assertSee('(C)');
        $response->assertSee('(WK)');
        $response->assertSee($bowler->name);
        $response->assertSee('Batter');
        $response->assertSee('Wicketkeeper');
        $response->assertSee('Bowler');

        // Bench players (on the squad, never given a Playing XI row) must
        // never appear as if they played.
        $response->assertDontSee($benchPlayerA->name);
        $response->assertDontSee($benchPlayerB->name);
    }

    public function test_squads_page_handles_unannounced_playing_xi_gracefully(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'scheduled',
        ]);

        $response = $this->get(route('public.matches.squads', $match));

        $response->assertOk();
        $response->assertSeeText('Playing XI not announced yet.');
    }

    public function test_squads_route_does_not_expose_private_player_data(): void
    {
        [$match, $striker] = $this->matchWithFullScorecard();
        $striker->update(['phone' => '9998887771', 'email' => 'striker@example.com']);

        $response = $this->get(route('public.matches.squads', $match));

        $response->assertOk();
        $response->assertDontSee('9998887771');
        $response->assertDontSee('striker@example.com');
    }
}
