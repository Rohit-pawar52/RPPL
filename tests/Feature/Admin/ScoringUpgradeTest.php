<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\ScoringEvent;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\GameMatch\MatchFlowService;
use App\Services\Innings\InningsService;
use App\Services\MatchPlayer\MatchPlayerService;
use App\Services\Scoring\DeliveryService;
use App\Services\Scoring\ScorecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase S02 — focused coverage for the frozen rules implemented this
 * phase (see the S02 completion report for the full rule-by-rule
 * breakdown). ScoringTest/StrikeRotationTest/AutomaticInningsCompletion
 * Test already cover the pre-existing engine; this file only covers
 * genuinely new behavior.
 */
class ScoringUpgradeTest extends TestCase
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
     * A live match, toss recorded, with a given number of selected
     * MatchPlayers per side and a live Innings #1.
     *
     * @return array{0: GameMatch, 1: Innings, 2: Collection<int, MatchPlayer>, 3: Collection<int, MatchPlayer>}
     */
    private function matchWithLiveInnings(int $playersPerSide = 11, array $matchAttributes = [], array $inningsAttributes = []): array
    {
        $match = GameMatch::factory()->create(array_merge([
            'match_status' => 'live',
            'started_at' => now(),
            'overs_per_innings' => 20,
        ], $matchAttributes));

        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $battingPlayers = collect(range(1, $playersPerSide))->map(fn () => MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]));

        $bowlingPlayers = collect(range(1, $playersPerSide))->map(fn () => MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]));

        $innings = Innings::create(array_merge([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id,
            'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'live',
        ], $inningsAttributes));

        return [$match->fresh(), $innings->fresh(), $battingPlayers, $bowlingPlayers];
    }

    private function score(User $user, GameMatch $match, Innings $innings, array $payload)
    {
        return $this->actingAs($user)->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), $payload);
    }

    /**
     * Only the scalar batting figures (never the Eloquent model objects
     * themselves, which are always distinct instances across two
     * separate scorecard computations) — for asserting a penalty leaves
     * every batter's figures byte-for-byte unchanged.
     */
    private function battingFigures(Innings $innings): array
    {
        return collect(app(ScorecardService::class)->getInningsScorecard($innings)['battingRows'])
            ->map(fn (array $row) => [
                'matchPlayerId' => $row['matchPlayer']->id,
                'runs' => $row['runs'],
                'balls' => $row['balls'],
                'fours' => $row['fours'],
                'sixes' => $row['sixes'],
                'strikeRate' => $row['strikeRate'],
                'dismissalText' => $row['dismissalText'],
            ])->all();
    }

    // ----- Rule 1: exactly 11 Playing XI -----

    public function test_toss_cannot_start_with_fewer_than_eleven_players_per_side(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);
        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 10; $i++) {
                MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id]);
            }
        }

        $this->assertFalse(app(MatchFlowService::class)->canStartToss($match));
    }

    public function test_toss_can_start_with_exactly_eleven_players_per_side(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);
        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 11; $i++) {
                MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id]);
            }
        }

        $this->assertTrue(app(MatchFlowService::class)->canStartToss($match));
    }

    /**
     * MatchPlayerService::addPlayer() (still used directly by the demo
     * seeders to build a Playing XI) independently enforces the max-11
     * cap — exercised here at the service level, since the old one-
     * player-at-a-time HTTP endpoint this test used to go through was
     * replaced by bulk Playing XI selection (see MatchPlayerController::
     * sync()), which the "more than eleven is rejected" coverage in
     * MatchPlayerManagementTest now owns for the HTTP path.
     */
    public function test_a_twelfth_player_cannot_be_added_to_the_playing_xi(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);
        $matchPlayers = app(MatchPlayerService::class);

        for ($i = 0; $i < 11; $i++) {
            $teamPlayer = TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id]);
            $matchPlayers->addPlayer($match, $teamPlayer);
        }
        $this->assertSame(11, MatchPlayer::where('match_id', $match->id)->count());

        $twelfth = TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id]);

        $this->expectException(ValidationException::class);
        $matchPlayers->addPlayer($match, $twelfth);
    }

    // ----- Rule 9: consecutive-over bowler hard-block -----

    public function test_same_bowler_cannot_bowl_consecutive_overs(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        for ($i = 0; $i < 6; $i++) {
            $this->score($this->admin(), $match, $innings, [
                'striker_match_player_id' => $battingPlayers[0]->id,
                'non_striker_match_player_id' => $battingPlayers[1]->id,
                'bowler_match_player_id' => $bowlingPlayers[0]->id,
                'runs_off_bat' => 0,
            ]);
        }
        $this->assertSame(6, $innings->fresh()->legal_balls);

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0,
        ])->assertSessionHasErrors('bowler_match_player_id');

        $this->assertSame(6, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_a_different_bowler_may_start_the_next_over(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        for ($i = 0; $i < 6; $i++) {
            $this->score($this->admin(), $match, $innings, [
                'striker_match_player_id' => $battingPlayers[0]->id,
                'non_striker_match_player_id' => $battingPlayers[1]->id,
                'bowler_match_player_id' => $bowlingPlayers[0]->id,
                'runs_off_bat' => 0,
            ]);
        }

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'bowler_match_player_id' => $bowlingPlayers[1]->id,
            'runs_off_bat' => 0,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(7, Delivery::where('innings_id', $innings->id)->count());
    }

    // ----- Rule 3: No Ball + Free Hit -----

    public function test_no_ball_makes_the_next_delivery_a_free_hit(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'is_no_ball' => 1,
        ]);

        $this->assertTrue(app(DeliveryService::class)->isFreeHit($innings->fresh()));

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 4,
        ]);

        $this->assertTrue(Delivery::where('innings_id', $innings->id)->latest('delivery_sequence')->first()->is_free_hit);
    }

    public function test_free_hit_continues_through_a_wide_and_ends_on_a_legal_delivery(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $deliveries = app(DeliveryService::class);

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_no_ball' => 1,
        ]);
        $this->assertTrue($deliveries->isFreeHit($innings->fresh()));

        // Free hit delivery is itself a wide — free hit must continue.
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_wide' => 1,
        ]);
        $this->assertTrue($deliveries->isFreeHit($innings->fresh()));

        // A legal delivery finally ends the free hit.
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);
        $this->assertFalse($deliveries->isFreeHit($innings->fresh()));
    }

    public function test_bowled_dismissal_is_rejected_on_a_free_hit(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_no_ball' => 1,
        ]);

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
            'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $battingPlayers[0]->id,
        ])->assertSessionHasErrors('wicket_type');

        $this->assertSame(0, $innings->fresh()->total_wickets);
    }

    public function test_run_out_is_valid_on_a_free_hit(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_no_ball' => 1,
        ]);

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
            'is_wicket' => 1, 'wicket_type' => 'run_out', 'dismissed_match_player_id' => $battingPlayers[1]->id,
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $innings->fresh()->total_wickets);
    }

    // ----- Rule 41: caught fielder optional, stumped still required -----

    public function test_stumped_still_requires_a_fielder(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
            'is_wicket' => 1, 'wicket_type' => 'stumped', 'dismissed_match_player_id' => $battingPlayers[0]->id,
        ])->assertSessionHasErrors('fielder_match_player_id');
    }

    // ----- Rule 10: wide run structure (penalty vs physically run) -----

    public function test_wide_with_physically_run_runs_is_split_from_the_mandatory_penalty(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_wide' => 1, 'wide_running_runs' => 2,
        ]);

        $delivery = Delivery::sole();
        $this->assertSame(1, $delivery->wide_runs);
        $this->assertSame(2, $delivery->wide_running_runs);
        $this->assertSame(3, $delivery->total_runs);
        $this->assertSame(3, $innings->fresh()->total_runs);
    }

    // ----- Rule 11: no-ball + byes/leg-byes combination -----

    public function test_no_ball_can_carry_byes_on_the_same_delivery(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_no_ball' => 1, 'bye_runs' => 2,
        ])->assertSessionDoesntHaveErrors();

        $delivery = Delivery::sole();
        $this->assertSame(1, $delivery->no_ball_runs);
        $this->assertSame(2, $delivery->bye_runs);
        $this->assertSame(3, $delivery->total_runs);
        $this->assertFalse($delivery->is_legal_delivery);
    }

    public function test_byes_are_rejected_on_a_wide(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'is_wide' => 1, 'bye_runs' => 2,
        ])->assertSessionHasErrors('bye_runs');
    }

    // ----- Rule 6: penalty runs -----

    public function test_penalty_to_current_batting_side_credits_extras_only_no_batter_bowler_ball_or_strike_change(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 1,
        ]);
        $stateBefore = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $battingRowsBefore = $this->battingFigures($innings->fresh());

        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $innings]), [
            'awarded_team_id' => $match->edition_team_a_id,
            'reason' => 'Fielding restriction breach',
        ])->assertRedirect();

        $fresh = $innings->fresh();
        $this->assertSame(6, $fresh->total_runs); // 1 (bat) + 5 (penalty)
        $this->assertSame(5, $fresh->extras);
        $this->assertSame(1, $fresh->legal_balls); // unchanged by the penalty
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count()); // no new Delivery

        // Strike state is unaffected by the penalty alone.
        $this->assertSame($stateBefore, app(DeliveryService::class)->expectedBattingState($fresh));

        // No batter or bowler figure changed.
        $this->assertSame($battingRowsBefore, $this->battingFigures($fresh));

        $event = ScoringEvent::sole();
        $this->assertSame(ScoringEvent::TYPE_PENALTY_RUNS, $event->type);
        $this->assertSame($match->edition_team_a_id, $event->awarded_team_id);
        $this->assertSame(5, $event->runs);
        $this->assertSame('Fielding restriction breach', $event->reason);
    }

    public function test_penalty_to_fielding_side_before_it_has_batted_carries_forward_to_its_own_innings(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $fieldingTeamId = $match->edition_team_b_id;

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $innings]), [
            'awarded_team_id' => $fieldingTeamId,
            'reason' => 'Slow over rate',
        ])->assertRedirect();

        // Team A's own (currently batting) innings is completely unaffected.
        $this->assertSame(0, $innings->fresh()->total_runs);
        $this->assertSame(0, $innings->fresh()->extras);

        // Complete innings #1 and start innings #2 for the fielding team.
        app(InningsService::class)->completeInnings($match, $innings->fresh(), 'End of innings');
        app(InningsService::class)->startSecondInnings($match->fresh());
        $second = $match->fresh()->secondInnings;

        $this->assertSame($fieldingTeamId, $second->batting_team_id);
        $this->assertSame(5, $second->total_runs);
        $this->assertSame(5, $second->extras);
        $this->assertSame(0, $second->legal_balls);
        $this->assertSame(0, Delivery::where('innings_id', $second->id)->count());

        // Still only ONE authoritative penalty event — never duplicated.
        $this->assertSame(1, ScoringEvent::where('type', ScoringEvent::TYPE_PENALTY_RUNS)->count());
    }

    public function test_penalty_to_fielding_side_after_it_already_batted_corrects_its_completed_innings_and_the_live_chase_target(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        // Team A bats 4 in innings #1, then it completes.
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 4,
        ]);
        app(InningsService::class)->completeInnings($match, $innings->fresh(), 'End of innings');
        app(InningsService::class)->startSecondInnings($match->fresh());
        $second = $match->fresh()->secondInnings;

        // Penalty awarded to Team A (the now-fielding side), which
        // already batted — must correct that completed innings.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $second]), [
            'awarded_team_id' => $match->edition_team_a_id,
            'reason' => 'Discovered after the fact',
        ])->assertRedirect();

        $first = $innings->fresh();
        $this->assertSame(9, $first->total_runs); // 4 + 5 penalty
        $this->assertSame(5, $first->extras);

        // The live chase target (first.total_runs + 1 = 10) must reflect
        // the corrected total, not the stale pre-penalty value (5).
        $this->score($this->admin(), $match, $second, [
            'striker_match_player_id' => $bowlingPlayers[0]->id, 'non_striker_match_player_id' => $bowlingPlayers[1]->id,
            'bowler_match_player_id' => $battingPlayers[0]->id, 'runs_off_bat' => 6, // 6 < corrected target 10, must not complete
        ]);
        $this->assertSame('live', $second->fresh()->status);

        $this->score($this->admin(), $match, $second, [
            'striker_match_player_id' => $bowlingPlayers[0]->id, 'non_striker_match_player_id' => $bowlingPlayers[1]->id,
            'bowler_match_player_id' => $battingPlayers[0]->id, 'runs_off_bat' => 4, // now 10 total, reaches corrected target
        ]);
        $this->assertSame('completed', $second->fresh()->status);
    }

    public function test_penalty_cannot_be_awarded_once_the_match_is_finalized(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);
        $match->update(['match_status' => 'completed', 'result_type' => 'won', 'match_result' => 'Team A won']);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $innings]), [
            'awarded_team_id' => $match->edition_team_a_id,
            'reason' => 'Too late',
        ])->assertSessionHasErrors('awarded_team_id');

        $this->assertSame(0, ScoringEvent::count());
    }

    public function test_recalculating_innings_totals_multiple_times_does_not_double_credit_a_penalty(): void
    {
        [$match, $innings] = $this->matchWithLiveInnings();
        $deliveries = app(DeliveryService::class);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $innings]), [
            'awarded_team_id' => $match->edition_team_a_id,
            'reason' => 'Fielding restriction breach',
        ]);

        $deliveries->recalculateInningsTotals($innings->fresh());
        $deliveries->recalculateInningsTotals($innings->fresh());
        $deliveries->recalculateInningsTotals($innings->fresh());

        $this->assertSame(5, $innings->fresh()->total_runs);
        $this->assertSame(1, ScoringEvent::count());
    }

    public function test_penalty_runs_require_a_reason(): void
    {
        [$match, $innings] = $this->matchWithLiveInnings();

        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $innings]), [
            'awarded_team_id' => $match->edition_team_a_id,
        ])->assertSessionHasErrors('reason');
    }

    // ----- Rules 4/5: Retired Hurt / Retired Out -----

    public function test_retired_hurt_is_not_a_wicket_and_batter_can_return(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.retire-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[0]->id,
            'type' => 'hurt',
            'reason' => 'Cramp',
        ])->assertRedirect();

        $this->assertSame(0, $innings->fresh()->total_wickets);

        // The retired batter can return as the new batter for the vacant end.
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ])->assertSessionDoesntHaveErrors();
    }

    public function test_retired_out_counts_as_a_wicket_and_batter_cannot_return(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.retire-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[0]->id,
            'type' => 'out',
            'reason' => 'Injury, cannot continue',
        ])->assertRedirect();

        $this->assertSame(1, $innings->fresh()->total_wickets);

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ])->assertSessionHasErrors('striker_match_player_id');
    }

    // ----- Rule 20: Change Strike -----

    public function test_change_strike_corrects_ends_without_a_delivery_or_score_change(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);
        $before = $innings->fresh()->only(['total_runs', 'total_wickets', 'legal_balls']);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.change-strike', [$match, $innings]), [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'reason' => 'Wrong ends recorded by scorer',
        ])->assertRedirect();

        $this->assertSame($before, $innings->fresh()->only(['total_runs', 'total_wickets', 'legal_balls']));
        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count() - 1); // still just the 1 real delivery

        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $this->assertSame($battingPlayers[1]->id, $state['striker_id']);
        $this->assertSame($battingPlayers[0]->id, $state['non_striker_id']);
    }

    // ----- Rule 15/16: manual innings completion + reopen -----

    public function test_manual_innings_completion_requires_a_reason_and_is_distinct_from_automatic(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $innings]))
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $innings]), ['reason' => 'Bad light'])
            ->assertRedirect();

        $fresh = $innings->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame('manual', $fresh->completion_type);
        $this->assertSame('Bad light', $fresh->completion_reason);

        // A manually-completed innings is never reopened by undo.
        $this->assertFalse(app(DeliveryService::class)->undoLastDelivery($match, $fresh));
    }

    public function test_reopening_a_manually_completed_innings_requires_a_reason_and_restores_live_status(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id, 'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id, 'runs_off_bat' => 0,
        ]);
        app(InningsService::class)->completeInnings($match, $innings->fresh(), 'Bad light');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.reopen', [$match, $innings]))
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.reopen', [$match, $innings]), ['reason' => 'Light improved, resuming play'])
            ->assertRedirect();

        $fresh = $innings->fresh();
        $this->assertSame('live', $fresh->status);
        $this->assertNull($fresh->completion_type);
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count()); // deliveries untouched

        $event = ScoringEvent::where('type', ScoringEvent::TYPE_INNINGS_REOPENED)->sole();
        $this->assertSame('Light improved, resuming play', $event->reason);
    }

    // ----- Rule 17: reopen finalized match, admin only -----

    public function test_reopening_a_finalized_match_is_admin_only_and_requires_a_reason(): void
    {
        $match = GameMatch::factory()->create([
            'match_status' => 'completed',
            'result_type' => 'won',
            'match_result' => 'Team A won by 10 runs',
        ]);

        $this->actingAs($this->scorer())
            ->post(route('admin.matches.reopen', $match), ['reason' => 'Scoring error found'])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.reopen', $match))
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.reopen', $match), ['reason' => 'Scoring error found after finalization'])
            ->assertRedirect();

        $fresh = $match->fresh();
        $this->assertSame('live', $fresh->match_status);
        $this->assertNull($fresh->result_type);
        $this->assertNull($fresh->winner_team_id);

        $event = ScoringEvent::where('type', ScoringEvent::TYPE_MATCH_RESULT_REOPENED)->sole();
        $this->assertSame('Scoring error found after finalization', $event->reason);
    }

    // ----- Rule 7: Super Over -----

    public function test_super_over_result_is_recorded_for_a_tied_match_without_touching_main_innings_scores(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'live']);
        $first = Innings::create([
            'match_id' => $match->id, 'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id, 'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'completed', 'total_runs' => 150, 'legal_balls' => 120,
        ]);
        $second = Innings::create([
            'match_id' => $match->id, 'innings_number' => 2,
            'batting_team_id' => $match->edition_team_b_id, 'bowling_team_id' => $match->edition_team_a_id,
            'status' => 'completed', 'total_runs' => 150, 'legal_balls' => 120,
        ]);

        $this->actingAs($this->admin())->post(route('admin.matches.super-over', $match), [
            'winner_team_id' => $match->edition_team_a_id,
            'reason' => 'Super Over: Team A 12/0, Team B 9/1',
        ])->assertRedirect();

        $fresh = $match->fresh();
        $this->assertSame('completed', $fresh->match_status);
        $this->assertSame('won', $fresh->result_type);
        $this->assertSame('super_over', $fresh->result_source);
        $this->assertSame($match->edition_team_a_id, $fresh->winner_team_id);
        $this->assertNull($fresh->win_margin);
        $this->assertStringContainsString('Super Over', $fresh->match_result);

        // Main innings scores are untouched.
        $this->assertSame(150, $first->fresh()->total_runs);
        $this->assertSame(150, $second->fresh()->total_runs);
    }

    public function test_super_over_result_rejected_when_match_is_not_actually_tied(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'live']);
        Innings::create([
            'match_id' => $match->id, 'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id, 'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'completed', 'total_runs' => 160, 'legal_balls' => 120,
        ]);
        Innings::create([
            'match_id' => $match->id, 'innings_number' => 2,
            'batting_team_id' => $match->edition_team_b_id, 'bowling_team_id' => $match->edition_team_a_id,
            'status' => 'completed', 'total_runs' => 150, 'legal_balls' => 120,
        ]);

        $this->actingAs($this->admin())->post(route('admin.matches.super-over', $match), [
            'winner_team_id' => $match->edition_team_a_id,
            'reason' => 'Not actually tied',
        ])->assertSessionHas('error');

        $this->assertSame('live', $match->fresh()->match_status);
    }
}
