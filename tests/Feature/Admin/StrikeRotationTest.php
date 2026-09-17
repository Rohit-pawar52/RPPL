<?php

namespace Tests\Feature\Admin;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StrikeRotationTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $deliveries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveries = app(DeliveryService::class);
    }

    /**
     * A live match, toss recorded, a generous (5-player) squad per side
     * — enough for the replacement-batter scenarios below without
     * running out of eligible new batters.
     */
    private function liveMatchWithSquads(array $matchAttributes = []): GameMatch
    {
        $match = GameMatch::factory()->create(array_merge([
            'match_status' => 'live',
            'started_at' => now(),
            'overs_per_innings' => 20,
        ], $matchAttributes));

        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 5; $i++) {
                MatchPlayer::factory()->create([
                    'match_id' => $match->id,
                    'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
                ]);
            }
        }

        return $match->fresh();
    }

    private function startInnings(GameMatch $match, int $number, int $battingTeamId, int $bowlingTeamId): Innings
    {
        return Innings::create([
            'match_id' => $match->id,
            'innings_number' => $number,
            'batting_team_id' => $battingTeamId,
            'bowling_team_id' => $bowlingTeamId,
            'status' => 'live',
        ]);
    }

    private function battingPlayers(int $editionTeamId): array
    {
        return MatchPlayer::query()
            ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $editionTeamId))
            ->get()->all();
    }

    private function bowler(int $editionTeamId): MatchPlayer
    {
        return MatchPlayer::query()
            ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $editionTeamId))
            ->first();
    }

    /**
     * Submits exactly the given data — no auto-computed defaults — so
     * these tests can deliberately submit both correct and incorrect
     * striker/non-striker pairs.
     */
    private function submit(GameMatch $match, Innings $innings, array $data): void
    {
        $this->deliveries->recordDelivery($match, $innings->fresh(), $data);
    }

    // ----- 1 & 12: first delivery is free choice; enforced thereafter -----

    public function test_first_delivery_is_free_choice_but_subsequent_mismatched_pair_is_rejected(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);

        // First ball: any valid batting-team pair is accepted.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $batting[1]->id,
            'non_striker_match_player_id' => $batting[3]->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);

        // Second ball: 0 runs, no over completion — expected pair is
        // unchanged. Submitting a different, unrelated pair is rejected.
        $this->expectException(ValidationException::class);
        $this->submit($match, $innings, [
            'striker_match_player_id' => $batting[0]->id,
            'non_striker_match_player_id' => $batting[2]->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);
    }

    // ----- 2 & 3: odd run swaps, even run preserves -----

    public function test_odd_run_swaps_strike_even_run_preserves_it(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b] = [$batting[0], $batting[1]];

        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 2, // even — no swap
        ]);
        // Same pair still valid.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 1, // odd — swaps
        ]);
        // Now the swapped pair is required.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 3, // odd again — swaps back
        ]);

        $this->assertSame(3, $innings->fresh()->legal_balls);
        $this->assertSame(6, $innings->fresh()->total_runs);

        // Submitting the OLD (pre-swap-back) pair is now rejected.
        $this->expectException(ValidationException::class);
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);
    }

    // ----- 4: byes/leg-byes rotate strike the same as bat runs -----

    public function test_bye_and_leg_bye_parity_rotates_strike_same_as_bat_runs(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b] = [$batting[0], $batting[1]];

        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'extra_type' => 'bye', 'extra_amount' => 1, // 1 bye — odd, swaps
        ]);
        // The swapped pair is now required.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'extra_type' => 'leg_bye', 'extra_amount' => 2, // 2 leg byes — even, no swap
        ]);
        // Same (still-swapped) pair remains valid.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);

        $this->assertSame(3, $innings->fresh()->total_runs);
    }

    // ----- 5: wide/no-ball penalty alone never swaps; no-ball + bat run does -----

    public function test_wide_and_no_ball_penalty_alone_do_not_swap_but_no_ball_with_bat_run_does(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b] = [$batting[0], $batting[1]];

        // A wide worth several penalty runs still must not swap strike —
        // the schema cannot distinguish "penalty" from "physically run"
        // within wide_runs, so it is deliberately excluded entirely
        // (see DeliveryService's docblock).
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 5,
        ]);
        // A bare no-ball (1 penalty run, 0 off the bat) also doesn't swap.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'extra_type' => 'no_ball', 'extra_amount' => 1,
        ]);
        // A no-ball WITH a batter run does swap, because of the bat run.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 1, 'extra_type' => 'no_ball', 'extra_amount' => 1,
        ]);
        // The swapped pair is now required.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);

        $this->assertSame(1, $innings->fresh()->legal_balls); // only the final ball is legal
    }

    // ----- 6 & 7: over-end swap, and cancellation with an odd run -----

    public function test_sixth_legal_ball_swaps_ends_and_an_odd_run_on_it_cancels_the_swap(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b] = [$batting[0], $batting[1]];

        for ($i = 0; $i < 5; $i++) {
            $this->submit($match, $innings, [
                'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
        }
        // 6th legal ball, 0 runs: over-swap only.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);
        $this->assertSame(6, $innings->fresh()->legal_balls);
        // The new over requires the SWAPPED pair.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);

        // Now the 6th ball of the SECOND over, scored with an odd run:
        // run-swap XOR over-swap cancel out, so the SAME batter who
        // faced it also faces the next (third) over.
        for ($i = 0; $i < 4; $i++) {
            $this->submit($match, $innings, [
                'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
        }
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 1, // odd run on the 6th (over-ending) ball
        ]);
        $this->assertSame(12, $innings->fresh()->legal_balls);

        // Third over: no net swap, so $b (unchanged) is still on strike.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $a->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);

        $this->assertSame(13, $innings->fresh()->legal_balls);
    }

    // ----- 8: wicket requires an eligible replacement; dismissed batter can't return -----

    public function test_wicket_requires_eligible_replacement_and_blocks_a_dismissed_batter_returning(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b, $c] = [$batting[0], $batting[1], $batting[2]];

        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $a->id,
        ]);

        // $b (survivor) must stay at the non-striker end; a NEW batter is
        // required at the vacant striker end.
        $this->assertTrue($this->deliveries->canRecordDelivery($match, $innings->fresh()));

        // Submitting $b at the WRONG (striker) end is rejected.
        try {
            $this->submit($match, $innings, [
                'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $c->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
            $this->fail('Expected a ValidationException for the survivor at the wrong end.');
        } catch (ValidationException) {
            // expected
        }

        // The dismissed batter (A) cannot return as the replacement.
        try {
            $this->submit($match, $innings, [
                'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
            $this->fail('Expected a ValidationException for a dismissed batter returning.');
        } catch (ValidationException) {
            // expected
        }

        // The correct replacement (C, at the vacant striker end) succeeds.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $c->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        $this->assertSame(4, $innings->fresh()->total_runs);
        $this->assertSame(1, $innings->fresh()->total_wickets);
    }

    // ----- 9: wicket on the final ball of an over -----

    public function test_wicket_on_final_ball_of_over_correctly_rotates_the_survivor(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b, $c] = [$batting[0], $batting[1], $batting[2]];

        for ($i = 0; $i < 5; $i++) {
            $this->submit($match, $innings, [
                'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
        }
        // 6th (over-ending) ball: A is out, 0 runs.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $a->id,
        ]);

        // Ignoring the wicket, 0 runs + over-end would put B on strike
        // for the new over. Since A (not B) was the one dismissed, B
        // keeps that same striker slot, and the new batter (C) fills
        // the vacant non-striker end.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $b->id, 'non_striker_match_player_id' => $c->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);

        $this->assertSame(7, $innings->fresh()->legal_balls);
        $this->assertSame(1, $innings->fresh()->total_wickets);
    }

    // ----- 10: undo naturally restores the expected state -----

    public function test_undo_naturally_restores_expected_state(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b, $c] = [$batting[0], $batting[1], $batting[2]];

        // Delivery 1: A scores 1 (odd) -> expected next striker is B.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 1,
        ]);

        $this->assertTrue($this->deliveries->undoLastDelivery($match, $innings->fresh()));

        // With no deliveries left, first-ball free choice applies again
        // — any valid pair (even a completely different one) succeeds.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $c->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);
        $this->assertSame(1, $innings->fresh()->legal_balls);

        // Now dismiss C, bring in A as the replacement, record a ball,
        // then undo it — the expected state must return to "C survives,
        // A is the replacement", not silently drift.
        $this->submit($match, $innings, [
            'striker_match_player_id' => $c->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $c->id,
        ]);
        $this->submit($match, $innings, [
            'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 2,
        ]);
        $this->assertTrue($this->deliveries->undoLastDelivery($match, $innings->fresh()));

        // A dismissed batter (C) still cannot return, proving the
        // dismissed-list is recalculated from remaining history, not a
        // stale record of the undone delivery's state.
        $this->expectException(ValidationException::class);
        $this->submit($match, $innings, [
            'striker_match_player_id' => $c->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ]);
    }

    // ----- 11: innings completion needs no next-batter state -----

    public function test_innings_completion_does_not_require_next_batter_state(): void
    {
        $match = $this->liveMatchWithSquads(['overs_per_innings' => 1]);
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $batting = $this->battingPlayers($match->edition_team_a_id);
        $bowler = $this->bowler($match->edition_team_b_id);
        [$a, $b] = [$batting[0], $batting[1]];

        for ($i = 0; $i < 6; $i++) {
            $this->submit($match, $innings, [
                'striker_match_player_id' => $a->id, 'non_striker_match_player_id' => $b->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
        }

        $completed = $innings->fresh();
        $this->assertSame('completed', $completed->status);

        // No further delivery is possible — there is no "next" state to
        // compute or enforce.
        $this->assertFalse($this->deliveries->canRecordDelivery($match, $completed));
    }
}
