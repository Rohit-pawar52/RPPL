<?php

namespace Tests\Feature\Admin;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\MatchPlayer\MatchPlayerService;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchFinalizationTest extends TestCase
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
     * A live match, toss already recorded (Team A batting first), two
     * selected MatchPlayers per side (enough for a valid distinct
     * striker/non-striker pair regardless of which side is batting).
     * Innings are added by the caller.
     */
    private function liveMatchWithSquads(array $matchAttributes = []): GameMatch
    {
        $match = GameMatch::factory()->create(array_merge([
            'match_status' => 'live',
            'started_at' => now(),
        ], $matchAttributes));

        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 2; $i++) {
                MatchPlayer::factory()->create([
                    'match_id' => $match->id,
                    'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
                ]);
            }
        }

        return $match->fresh();
    }

    private function addInnings(GameMatch $match, int $number, int $battingTeamId, int $bowlingTeamId, string $status, int $runs = 0, int $wickets = 0): Innings
    {
        return Innings::create([
            'match_id' => $match->id,
            'innings_number' => $number,
            'batting_team_id' => $battingTeamId,
            'bowling_team_id' => $bowlingTeamId,
            'status' => $status,
            'total_runs' => $runs,
            'total_wickets' => $wickets,
        ]);
    }

    /**
     * Both innings completed, ready for finalization: Team A batted
     * first, Team B chased.
     */
    private function matchReadyToFinalize(int $firstRuns, int $firstWickets, int $secondRuns, int $secondWickets, array $matchAttributes = []): GameMatch
    {
        $match = $this->liveMatchWithSquads($matchAttributes);

        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', $firstRuns, $firstWickets);
        $this->addInnings($match, 2, $match->edition_team_b_id, $match->edition_team_a_id, 'completed', $secondRuns, $secondWickets);

        return $match->fresh();
    }

    // ----- Authorization -----

    public function test_guest_cannot_finalize(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->post(route('admin.matches.finalize', $match))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_finalize(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertSame('completed', $match->fresh()->match_status);
    }

    public function test_scorer_can_finalize(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.finalize', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertSame('completed', $match->fresh()->match_status);
    }

    public function test_scorer_still_cannot_use_admin_fixture_crud(): void
    {
        $match = GameMatch::factory()->create();

        $this->actingAs($this->scorer())
            ->put(route('admin.matches.update', $match), [
                'edition_id' => $match->edition_id,
                'edition_team_a_id' => $match->edition_team_a_id,
                'edition_team_b_id' => $match->edition_team_b_id,
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->actingAs($this->scorer())
            ->delete(route('admin.matches.destroy', $match))
            ->assertForbidden();
    }

    // ----- Result calculation -----

    public function test_first_innings_team_wins_by_runs(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $teamAName = $match->teamA->team->name;

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $fresh = $match->fresh();
        $this->assertSame($match->edition_team_a_id, $fresh->winner_team_id);
        $this->assertSame('won', $fresh->result_type);
        $this->assertSame('runs', $fresh->win_margin_type);
        $this->assertSame(10, $fresh->win_margin);
        $this->assertSame("{$teamAName} won by 10 runs", $fresh->match_result);
    }

    public function test_chasing_team_wins_by_wickets(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 151, 6);
        $teamBName = $match->teamB->team->name;

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $fresh = $match->fresh();
        $this->assertSame($match->edition_team_b_id, $fresh->winner_team_id);
        $this->assertSame('won', $fresh->result_type);
        $this->assertSame('wickets', $fresh->win_margin_type);
        $this->assertSame(4, $fresh->win_margin);
        $this->assertSame("{$teamBName} won by 4 wickets", $fresh->match_result);
    }

    public function test_tied_scores_produce_tied_result(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 150, 9);

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $fresh = $match->fresh();
        $this->assertNull($fresh->winner_team_id);
        $this->assertSame('tied', $fresh->result_type);
        $this->assertNull($fresh->win_margin_type);
        $this->assertNull($fresh->win_margin);
        $this->assertSame('Match tied', $fresh->match_result);
    }

    // ----- Eligibility -----

    public function test_cannot_finalize_without_innings_one(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 2, $match->edition_team_b_id, $match->edition_team_a_id, 'completed', 100, 5);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
    }

    public function test_cannot_finalize_without_innings_two(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', 150, 8);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
    }

    public function test_cannot_finalize_while_innings_one_live(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live', 60, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
    }

    public function test_cannot_finalize_while_innings_two_live(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', 150, 8);
        $this->addInnings($match, 2, $match->edition_team_b_id, $match->edition_team_a_id, 'live', 60, 2);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
    }

    public function test_cannot_finalize_scheduled_or_toss_match(): void
    {
        foreach (['scheduled', 'toss'] as $status) {
            $match = GameMatch::factory()->create(['match_status' => $status]);

            $this->actingAs($this->admin())
                ->post(route('admin.matches.finalize', $match))
                ->assertSessionHas('error');

            $this->assertSame($status, $match->fresh()->match_status);
        }
    }

    public function test_cannot_finalize_already_completed_match(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));
        $storedResult = $match->fresh()->match_result;

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame($storedResult, $match->fresh()->match_result);
    }

    public function test_inconsistent_innings_team_identities_rejected(): void
    {
        $match = $this->liveMatchWithSquads();
        // Both innings wrongly record Team A as the batting side —
        // corrupt/inconsistent data that must never be guessed at.
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', 150, 8);
        $this->addInnings($match, 2, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', 140, 10);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
        $this->assertNull($match->fresh()->match_result);
    }

    // ----- Atomicity / lifecycle -----

    public function test_finalization_sets_completed_at(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);

        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));

        $this->assertNotNull($match->fresh()->completed_at);
    }

    public function test_repeated_finalization_fails_safely_and_preserves_result(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));
        $fresh = $match->fresh();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertSessionHas('error');

        $refetched = $match->fresh();
        $this->assertSame($fresh->match_result, $refetched->match_result);
        $this->assertSame($fresh->win_margin, $refetched->win_margin);
        $this->assertEquals($fresh->completed_at, $refetched->completed_at);
    }

    public function test_completed_match_cannot_record_or_undo_delivery(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));
        $fresh = $match->fresh();
        $secondInnings = $fresh->secondInnings;

        $battingPlayers = MatchPlayer::query()
            ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $secondInnings->batting_team_id))
            ->get();
        $bowlingPlayer = MatchPlayer::query()
            ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $secondInnings->bowling_team_id))
            ->first();

        $deliveryService = app(DeliveryService::class);
        $this->assertFalse($deliveryService->canRecordDelivery($fresh, $secondInnings));
        $this->assertFalse($deliveryService->undoLastDelivery($fresh, $secondInnings));

        // A validly-shaped payload (real striker/non-striker/bowler from
        // the correct sides), so StoreDeliveryRequest's own validation
        // passes and it is genuinely the match-state gate in the
        // controller/service that rejects this — not a coincidental
        // validation error.
        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.deliveries.store', [$fresh, $secondInnings]), [
                'striker_match_player_id' => $battingPlayers[0]->id,
                'non_striker_match_player_id' => $battingPlayers[1]->id,
                'bowler_match_player_id' => $bowlingPlayer->id,
                'runs_off_bat' => 1,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $secondInnings->deliveries()->count());
    }

    public function test_completed_match_playing_xi_remains_locked(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));
        $fresh = $match->fresh();

        $this->assertFalse(app(MatchPlayerService::class)->canModifyPlayingXI($fresh));

        $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $fresh->edition_team_a_id]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.players.store', $fresh), ['team_player_id' => $teamPlayer->id])
            ->assertRedirect(route('admin.matches.players.index', $fresh))
            ->assertSessionHas('error');
    }

    public function test_scorecard_and_match_show_display_final_result_after_completion(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $this->actingAs($this->admin())->post(route('admin.matches.finalize', $match));
        $teamAName = $match->fresh()->teamA->team->name;

        $showResponse = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));
        $showResponse->assertOk();
        $showResponse->assertSee("{$teamAName} won by 10 runs");
        $showResponse->assertDontSee('Finalize Match');

        $scorecardResponse = $this->actingAs($this->admin())->get(route('admin.matches.scorecard', $match));
        $scorecardResponse->assertOk();
        $scorecardResponse->assertSee("{$teamAName} won by 10 runs");
    }

    public function test_result_preview_and_finalize_button_shown_before_finalization(): void
    {
        $match = $this->matchReadyToFinalize(150, 8, 140, 10);
        $teamAName = $match->teamA->team->name;

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee('Expected result:');
        $response->assertSee("{$teamAName} won by 10 runs");
        $response->assertSee('Finalize Match');
    }
}
