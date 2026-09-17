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

class PlayerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Registers $player into $edition's $team, records one scored
     * delivery (a boundary as striker) in a live match so the player has
     * real career statistics and match history to display.
     *
     * @return array{0: PlayerRegistration, 1: GameMatch}
     */
    private function registerAndScore(Player $player, Edition $edition, EditionTeam $battingTeam, EditionTeam $bowlingTeam): array
    {
        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'registration_fee' => 2500.00,
            'payment_status' => 'paid',
        ]);
        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $battingTeam->id, 'player_registration_id' => $registration->id]);

        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $battingTeam->id,
            'edition_team_b_id' => $bowlingTeam->id,
            'match_status' => 'live',
            'started_at' => now(),
        ]);
        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $battingTeam->id,
            'bowling_team_id' => $bowlingTeam->id,
            'status' => 'live',
        ]);

        $striker = MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => $teamPlayer->id]);
        $nonStriker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $battingTeam->id])->id,
        ]);
        $bowler = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $bowlingTeam->id])->id,
        ]);

        app(DeliveryService::class)->recordDelivery($match, $innings->fresh(), [
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        $match->update(['match_status' => 'completed', 'match_result' => $battingTeam->team->name.' won']);

        return [$registration, $match->fresh()];
    }

    public function test_directory_shows_active_players_and_hides_inactive_ones(): void
    {
        $active = Player::factory()->create(['name' => 'Active Ashwin', 'is_active' => true]);
        $inactive = Player::factory()->create(['name' => 'Retired Raina', 'is_active' => false]);

        $response = $this->get(route('public.players.index'));

        $response->assertOk();
        $response->assertSee($active->name);
        $response->assertDontSee($inactive->name);
    }

    public function test_name_search_filters_the_directory(): void
    {
        Player::factory()->create(['name' => 'Virat Kohli', 'is_active' => true]);
        Player::factory()->create(['name' => 'Rohit Sharma', 'is_active' => true]);

        $response = $this->get(route('public.players.index', ['search' => 'Kohli']));

        $response->assertOk();
        $response->assertSee('Virat Kohli');
        $response->assertDontSee('Rohit Sharma');
    }

    public function test_inactive_players_historical_profile_remains_accessible(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $player = Player::factory()->create(['is_active' => false]);
        $this->registerAndScore($player, $edition, $teamA, $teamB);

        $response = $this->get(route('public.players.show', $player));

        $response->assertOk();
        $response->assertSee($player->name);
    }

    public function test_profile_renders_career_statistics_and_match_history_with_link(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $player = Player::factory()->create();
        [, $match] = $this->registerAndScore($player, $edition, $teamA, $teamB);

        $response = $this->get(route('public.players.show', $player));

        $response->assertOk();
        $response->assertSee('4'); // runs, from PlayerStatisticsService — not recomputed here
        $response->assertSee($teamA->team->name.' vs '.$teamB->team->name);
        $response->assertSee(route('public.matches.show', $match), false);
    }

    public function test_edition_filter_only_accepts_players_own_editions(): void
    {
        $editionA = Edition::factory()->create(['name' => 'RPPL 2024']);
        $editionB = Edition::factory()->create(['name' => 'RPPL 2025']);
        $teamA1 = EditionTeam::factory()->create(['edition_id' => $editionA->id]);
        $teamA2 = EditionTeam::factory()->create(['edition_id' => $editionA->id]);
        $player = Player::factory()->create();
        $this->registerAndScore($player, $editionA, $teamA1, $teamA2);

        // Filtering by an edition this player never played in must be
        // safely ignored, exactly like the admin player page — not a
        // 404 or an exception.
        $response = $this->get(route('public.players.show', ['player' => $player, 'edition_id' => $editionB->id]));

        $response->assertOk();
        $response->assertSee('4'); // still shows career figures, filter silently unapplied
    }

    public function test_public_pages_never_expose_private_player_or_registration_data(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $player = Player::factory()->create(['phone' => '9990001111', 'email' => 'private@example.com']);
        $this->registerAndScore($player, $edition, $teamA, $teamB);

        foreach ([route('public.players.index'), route('public.players.show', $player)] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertDontSee($player->phone);
            $response->assertDontSee($player->email);
            $response->assertDontSee('2500.00');
            $response->assertDontSee('paid');
        }
    }
}
