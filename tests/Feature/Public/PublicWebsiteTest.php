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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Edition, 1: EditionTeam, 2: EditionTeam}
     */
    private function editionWithTwoTeams(array $editionAttributes = []): array
    {
        $edition = Edition::factory()->create($editionAttributes);
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        return [$edition, $teamA, $teamB];
    }

    /**
     * A live match with a scored delivery — striker/bowler carry real
     * Player records (with phone/email set) so privacy assertions have
     * something to check against.
     *
     * @return array{0: GameMatch, 1: Player, 2: Player}
     */
    private function liveMatchWithScoring(Edition $edition, EditionTeam $teamA, EditionTeam $teamB): array
    {
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'live',
            'started_at' => now(),
        ]);
        $match->update(['toss_winner_team_id' => $teamA->id, 'toss_decision' => 'bat']);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ]);

        $batter = Player::factory()->create(['phone' => '9998887771', 'email' => 'batter@example.com']);
        $batterRegistration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $batter->id,
            'registration_fee' => 1234.56,
            'payment_status' => 'paid',
        ]);
        $batterTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $batterRegistration->id]);
        $striker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $batterTeamPlayer->id]);

        $nonStrikerTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id]);
        $nonStriker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $nonStrikerTeamPlayer->id]);

        $bowlerPlayer = Player::factory()->create(['phone' => '9998887772', 'email' => 'bowler@example.com']);
        $bowlerRegistration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $bowlerPlayer->id]);
        $bowlerTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamB->id, 'player_registration_id' => $bowlerRegistration->id]);
        $bowler = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $bowlerTeamPlayer->id]);

        app(DeliveryService::class)->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        return [$match->fresh(), $batter, $bowlerPlayer];
    }

    // ----- Basic access / empty states -----

    public function test_guest_can_access_public_pages_and_they_handle_empty_data_gracefully(): void
    {
        $this->get(route('public.home'))->assertOk()->assertSee('No tournament editions available yet.');
        $this->get(route('public.editions.index'))->assertOk()->assertSee('No tournament editions available yet.');
        $this->get(route('public.matches.index'))->assertOk()->assertSee('No matches scheduled yet.');
    }

    // ----- Edition page -----

    public function test_edition_page_shows_teams_standings_statistics_and_keeps_inactive_teams(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        [$match, $batter] = $this->liveMatchWithScoring($edition, $teamA, $teamB);
        $teamA->team->update(['is_active' => false]);

        $response = $this->get(route('public.editions.show', $edition));

        $response->assertOk();
        $response->assertSee($teamA->team->name); // inactive team still appears
        $response->assertSee($teamB->team->name);
        $response->assertSee('Points Table');
        $response->assertSee('Top Run Scorers');
        $response->assertSee('Top Wicket Takers');
        $response->assertSee('Records');
        $response->assertSee($batter->name); // appears in leaderboard
        $response->assertDontSee('excluded because'); // no admin integrity warning on public page
    }

    // ----- Match pages -----

    public function test_scheduled_match_and_completed_match_render_correctly(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();

        $scheduled = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'scheduled',
        ]);

        $response = $this->get(route('public.matches.show', $scheduled));
        $response->assertOk();
        $response->assertSee($teamA->team->name);
        $response->assertDontSee('Toss'); // no toss recorded yet
        $response->assertDontSee('Scorecard'); // no innings yet — no misleading link

        $completed = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'completed',
            'result_type' => 'won',
            'winner_team_id' => $teamA->id,
            'match_result' => "{$teamA->team->name} won by 10 runs",
            'toss_winner_team_id' => $teamA->id,
            'toss_decision' => 'bat',
        ]);

        $response = $this->get(route('public.matches.show', $completed));
        $response->assertOk();
        $response->assertSee("{$teamA->team->name} won by 10 runs");
        $response->assertSee('won the toss and chose to bat');
    }

    public function test_live_match_shows_cached_innings_score(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        [$match] = $this->liveMatchWithScoring($edition, $teamA, $teamB);

        $response = $this->get(route('public.matches.show', $match));

        $response->assertOk();
        $response->assertSee('4/0'); // cached Innings totals, not recalculated
        $response->assertSee('Scorecard'); // innings exists — link shown
    }

    // ----- Scorecard -----

    public function test_public_scorecard_uses_existing_calculated_figures_and_is_unavailable_before_innings(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        [$match, $batter, $bowler] = $this->liveMatchWithScoring($edition, $teamA, $teamB);

        $response = $this->get(route('public.matches.scorecard', $match));
        $response->assertOk();
        $response->assertSee($batter->name);
        $response->assertSee('4'); // runs scored, from ScorecardService — not recomputed here

        $noInningsMatch = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'scheduled',
        ]);

        $this->get(route('public.matches.scorecard', $noInningsMatch))
            ->assertRedirect(route('public.matches.show', $noInningsMatch));
    }

    // ----- Privacy -----

    public function test_public_pages_never_expose_private_player_or_registration_data(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        [$match, $batter, $bowler] = $this->liveMatchWithScoring($edition, $teamA, $teamB);

        foreach ([
            route('public.matches.show', $match),
            route('public.matches.scorecard', $match),
            route('public.editions.show', $edition),
        ] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertDontSee($batter->phone);
            $response->assertDontSee($batter->email);
            $response->assertDontSee($bowler->phone);
            $response->assertDontSee($bowler->email);
            $response->assertDontSee('1234.56'); // registration_fee
        }
    }

    // ----- Security -----

    public function test_public_pages_contain_no_admin_mutation_controls(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        [$match] = $this->liveMatchWithScoring($edition, $teamA, $teamB);

        $response = $this->get(route('public.matches.show', $match));
        $response->assertOk();
        $response->assertDontSee('Finalize Match');
        $response->assertDontSee('Start Toss');
        $response->assertDontSee('Manage Playing XI');
        $response->assertDontSee(route('admin.matches.finalize', $match), false);
    }

    public function test_existing_admin_routes_still_require_authentication(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.editions.index'))->assertRedirect(route('admin.login'));
    }

    // ----- Query behaviour -----

    public function test_edition_and_match_index_pages_do_not_scale_queries_with_data_volume(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();

        foreach (range(1, 6) as $i) {
            GameMatch::factory()->create([
                'edition_id' => $edition->id,
                'edition_team_a_id' => $teamA->id,
                'edition_team_b_id' => $teamB->id,
                'match_status' => 'completed',
                'result_type' => 'won',
                'winner_team_id' => $teamA->id,
                'match_result' => 'Result',
            ]);
        }

        DB::enableQueryLog();
        $this->get(route('public.editions.show', $edition))->assertOk();
        $editionQueries = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->get(route('public.matches.index'))->assertOk();
        $matchIndexQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // A generous ceiling — the point is that this stays bounded and
        // small regardless of match count, not an exact query budget.
        $this->assertLessThan(30, $editionQueries);
        $this->assertLessThan(30, $matchIndexQueries);
    }
}
