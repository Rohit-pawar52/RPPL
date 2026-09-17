<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Thin integration coverage for wiring PlayerStatisticsService into the
 * existing admin.players.show / admin.editions.show pages. Calculation
 * correctness itself is covered by PlayerStatisticsServiceTest.
 */
class PlayerStatisticsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    /**
     * A player who has actually scored in a live match under a real
     * Edition, ready to exercise both admin pages.
     *
     * @return array{0: Player, 1: Edition, 2: GameMatch}
     */
    private function playerWithMatchHistory(): array
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

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ]);

        $player = Player::factory()->create();
        $registration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $player->id]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $registration->id]);
        $striker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);

        $nonStrikerTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamA->id]);
        $nonStriker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $nonStrikerTeamPlayer->id]);
        $bowlerTeamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $teamB->id]);
        $bowler = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $bowlerTeamPlayer->id]);

        app(DeliveryService::class)->recordDelivery($match->fresh(), $innings->fresh(), [
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        return [$player, $edition, $match->fresh()];
    }

    public function test_player_show_renders_career_stats_edition_filter_and_match_history(): void
    {
        [$player, $edition, $match] = $this->playerWithMatchHistory();

        $response = $this->actingAs($this->admin())->get(route('admin.players.show', $player));

        $response->assertOk();
        $response->assertSee('Batting');
        $response->assertSee('Bowling');
        $response->assertSee('Match History');
        $response->assertSee($edition->name);
        $response->assertSee($match->teamA->team->name);
        $response->assertSee('4 (1)*'); // 4 runs off 1 ball, not out
    }

    public function test_player_show_edition_filter_isolates_stats(): void
    {
        [$player, $edition] = $this->playerWithMatchHistory();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.players.show', ['player' => $player, 'edition_id' => $edition->id]));

        $response->assertOk();
        $response->assertSee('4', false); // runs still shown for this edition

        // edition_id is whitelisted against the player's own
        // registrations (PlayerController::show()) — an edition they
        // were never registered in doesn't match any registration, so
        // it safely falls back to Career rather than erroring or
        // trusting an arbitrary id.
        $otherEdition = Edition::factory()->create();
        $response = $this->actingAs($this->admin())
            ->get(route('admin.players.show', ['player' => $player, 'edition_id' => $otherEdition->id]));

        $response->assertOk();
        $response->assertSee('Career / All Editions');
        $response->assertSee('4 (1)*');
    }

    public function test_edition_show_renders_leaderboards_and_records(): void
    {
        [$player, $edition] = $this->playerWithMatchHistory();

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Top Run Scorers');
        $response->assertSee('Top Wicket Takers');
        $response->assertSee('Records');
        $response->assertSee($player->name);
    }

    public function test_edition_show_renders_cleanly_with_no_scoring_data(): void
    {
        $edition = Edition::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('No batting data yet.');
        $response->assertSee('No bowling data yet.');
    }
}
