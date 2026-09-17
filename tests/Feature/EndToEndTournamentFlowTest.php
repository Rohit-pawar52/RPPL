<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Venue;
use App\Services\Scoring\DeliveryService;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.36 — one high-value end-to-end test that walks a single,
 * realistic 1-over-per-side match through the ACTUAL admin HTTP
 * workflow (toss -> start -> first innings -> scoring -> automatic
 * completion -> second innings -> chase -> finalize), then verifies the
 * result is visible through every downstream consumer: the stored
 * GameMatch result, both innings' cached totals, the admin and public
 * scorecards, player statistics, and the edition standings. This does
 * not replace the many focused unit/feature tests already covering each
 * step in isolation — it exists to prove the steps compose correctly
 * end to end, which no single existing test does.
 */
class EndToEndTournamentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Registers, squads, and playing-XI-selects $count players for one
     * EditionTeam. Returns the created MatchPlayer rows in order.
     *
     * @return list<MatchPlayer>
     */
    private function fieldSquad(GameMatch $match, Edition $edition, EditionTeam $editionTeam, int $count): array
    {
        $matchPlayers = [];

        for ($i = 0; $i < $count; $i++) {
            $player = Player::factory()->create();
            $registration = PlayerRegistration::factory()->create([
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => 'paid',
            ]);
            $teamPlayer = TeamPlayer::factory()->create([
                'edition_team_id' => $editionTeam->id,
                'player_registration_id' => $registration->id,
            ]);
            $matchPlayers[] = MatchPlayer::factory()->create([
                'match_id' => $match->id,
                'team_player_id' => $teamPlayer->id,
            ]);
        }

        return $matchPlayers;
    }

    /**
     * Submits one legal, non-wicket delivery for the given number of
     * batter runs, resolving the correct striker/non-striker pair via
     * DeliveryService::expectedBattingState() (the same production
     * logic being exercised) rather than hand-tracking strike rotation
     * in the test — Phase 3.33 established this pattern.
     */
    private function bowlBall($admin, GameMatch $match, $innings, array $battingPair, MatchPlayer $bowler, int $runs): void
    {
        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());

        [$striker, $nonStriker] = $state['first_ball']
            ? [$battingPair[0], $battingPair[1]]
            : [
                collect($battingPair)->firstWhere('id', $state['striker_id']),
                collect($battingPair)->firstWhere('id', $state['non_striker_id']),
            ];

        $this->actingAs($admin)
            ->post(route('admin.matches.innings.deliveries.store', ['match' => $match, 'innings' => $innings]), [
                'striker_match_player_id' => $striker->id,
                'non_striker_match_player_id' => $nonStriker->id,
                'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => $runs,
            ])->assertRedirect();
    }

    public function test_a_complete_one_over_match_flows_correctly_through_every_downstream_consumer(): void
    {
        $admin = $this->admin();

        $edition = Edition::factory()->create(['status' => 'active']);
        $venue = Venue::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'venue_id' => $venue->id,
            'overs_per_innings' => 1,
            'match_status' => 'scheduled',
        ]);

        $squadA = $this->fieldSquad($match, $edition, $teamA, 3);
        $squadB = $this->fieldSquad($match, $edition, $teamB, 3);

        // ----- Toss & start -----
        $this->actingAs($admin)->post(route('admin.matches.start-toss', $match))->assertRedirect();
        $this->actingAs($admin)->put(route('admin.matches.toss.update', $match), [
            'toss_winner_team_id' => $teamA->id,
            'toss_decision' => 'bat',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.matches.start', $match))->assertRedirect();

        $match->refresh();
        $this->assertSame('live', $match->match_status);

        // ----- First innings: Team A bats, 6 legal balls, no wickets -----
        $this->actingAs($admin)->post(route('admin.matches.innings.first.start', $match))->assertRedirect();

        $firstInnings = $match->firstInnings()->firstOrFail();
        $this->assertSame($teamA->id, $firstInnings->batting_team_id);

        foreach ([4, 2, 4, 0, 0, 0] as $runs) {
            $this->bowlBall($admin, $match, $firstInnings, $squadA, $squadB[0], $runs);
        }

        $firstInnings->refresh();
        $this->assertSame('completed', $firstInnings->status);
        $this->assertSame(10, $firstInnings->total_runs);
        $this->assertSame(0, $firstInnings->total_wickets);
        $this->assertSame(6, $firstInnings->legal_balls);

        // ----- Second innings: Team B chases, reaches the target early -----
        $this->actingAs($admin)->post(route('admin.matches.innings.second.start', $match))->assertRedirect();

        $secondInnings = $match->fresh()->secondInnings()->firstOrFail();
        $this->assertSame($teamB->id, $secondInnings->batting_team_id);

        foreach ([4, 4, 4] as $runs) {
            $this->bowlBall($admin, $match, $secondInnings, $squadB, $squadA[0], $runs);
        }

        $secondInnings->refresh();
        $this->assertSame('completed', $secondInnings->status, 'reaching the target must auto-complete the chase before all legal balls are bowled');
        $this->assertSame(12, $secondInnings->total_runs);
        $this->assertLessThan(6, $secondInnings->legal_balls, 'the chase must finish mid-over, proving automatic completion fired on the target being reached rather than on overs running out');

        // Automatic innings completion must never finalize the match itself.
        $this->assertSame('live', $match->fresh()->match_status);

        // ----- Finalize -----
        $this->actingAs($admin)->post(route('admin.matches.finalize', $match))->assertRedirect();

        $match = $match->fresh();
        $this->assertSame('completed', $match->match_status);
        $this->assertSame($teamB->id, $match->winner_team_id);
        $this->assertSame('won', $match->result_type);
        $this->assertNotEmpty($match->match_result);
        $this->assertStringContainsString('won by', $match->match_result);

        // ----- Scorecards -----
        $this->actingAs($admin)->get(route('admin.matches.scorecard', $match))->assertOk();
        $this->get(route('public.matches.scorecard', $match))->assertOk();
        $this->get(route('public.matches.show', $match))->assertOk();

        // ----- Player statistics reflect the recorded deliveries -----
        $strikerAPlayer = $squadA[0]->teamPlayer->playerRegistration->player;
        $stats = app(PlayerStatisticsService::class)->getPlayerStatistics($strikerAPlayer, $edition);
        $this->assertSame(1, $stats['matches_played']);

        // ----- Standings reflect the completed result -----
        $standings = app(StandingsService::class)->getEditionStandings($edition);
        $rows = collect($standings['standings'])->keyBy(fn ($row) => $row['edition_team']->id);

        $this->assertSame(1, $rows[$teamB->id]['won']);
        $this->assertSame(1, $rows[$teamA->id]['lost']);
        $this->assertGreaterThan($rows[$teamA->id]['points'], $rows[$teamB->id]['points']);
    }
}
