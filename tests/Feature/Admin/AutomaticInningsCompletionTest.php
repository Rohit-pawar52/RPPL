<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Innings\InningsService;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AutomaticInningsCompletionTest extends TestCase
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
     * A live match, toss recorded, with a GENEROUS squad per side (12 —
     * comfortably enough for all ten wickets of an innings to fall,
     * each needing a genuinely new, not-yet-dismissed replacement
     * batter under Phase 3.33's strike-rotation enforcement).
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
            for ($i = 0; $i < 12; $i++) {
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
     * Derives the correct striker/non-striker for the next delivery
     * directly from DeliveryService::expectedBattingState() (Phase
     * 3.33) — reusing the exact same production logic these tests are
     * indirectly exercising, rather than hand-predicting it, so the
     * fixture never drifts out of sync with the real rule.
     *
     * @return array{0: MatchPlayer, 1: MatchPlayer}
     */
    private function nextBattingPair(Innings $innings, array $battingPlayers): array
    {
        $deliveries = app(DeliveryService::class);
        $state = $deliveries->expectedBattingState($innings->fresh());

        if ($state['first_ball']) {
            return [$battingPlayers[0], $battingPlayers[1]];
        }

        $byId = fn (int $id) => collect($battingPlayers)->sole(fn ($p) => $p->id === $id);

        if ($state['requires_replacement']) {
            $dismissed = $deliveries->dismissedMatchPlayerIds($innings->fresh());
            $survivor = $byId($state['survivor_id']);
            $newBatter = collect($battingPlayers)->first(
                fn ($p) => $p->id !== $state['survivor_id'] && ! in_array($p->id, $dismissed, true)
            );

            return $state['survivor_end'] === 'striker' ? [$survivor, $newBatter] : [$newBatter, $survivor];
        }

        return [$byId($state['striker_id']), $byId($state['non_striker_id'])];
    }

    private function ball(GameMatch $match, Innings $innings, array $overrides = []): Delivery
    {
        $fresh = $innings->fresh();
        $batting = $this->battingPlayers($fresh->batting_team_id);
        [$striker, $nonStriker] = $this->nextBattingPair($fresh, $batting);
        $bowler = $this->bowler($fresh->bowling_team_id);

        return app(DeliveryService::class)->recordDelivery($match, $fresh, array_merge([
            'striker_match_player_id' => $striker->id,
            'non_striker_match_player_id' => $nonStriker->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0,
        ], $overrides));
    }

    /**
     * Dismisses whichever batter is correctly on strike for the next
     * ball — always a legitimate target, however many wickets have
     * already fallen this innings.
     */
    private function wicketBall(GameMatch $match, Innings $innings): Delivery
    {
        $fresh = $innings->fresh();
        $batting = $this->battingPlayers($fresh->batting_team_id);
        [$striker] = $this->nextBattingPair($fresh, $batting);

        return $this->ball($match, $innings, [
            'is_wicket' => true,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $striker->id,
        ]);
    }

    public function test_tenth_wicket_automatically_completes_innings_and_undo_restores_it(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);

        for ($i = 0; $i < 9; $i++) {
            $this->wicketBall($match, $innings);
        }
        $this->assertSame('live', $innings->fresh()->status);

        $this->wicketBall($match, $innings);
        $completed = $innings->fresh();
        $this->assertSame('completed', $completed->status);
        $this->assertSame(10, $completed->total_wickets);

        // Match itself is untouched — no auto-finalization.
        $this->assertSame('live', $match->fresh()->match_status);
        $this->assertNull($match->fresh()->winner_team_id);

        // No further delivery may be recorded once auto-completed.
        $this->assertFalse(app(DeliveryService::class)->canRecordDelivery($match, $completed));
        $this->expectException(ValidationException::class);
        $this->ball($match, $completed);
    }

    public function test_undoing_the_tenth_wicket_returns_the_innings_to_live(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);

        for ($i = 0; $i < 10; $i++) {
            $this->wicketBall($match, $innings);
        }
        $this->assertSame('completed', $innings->fresh()->status);

        $this->assertTrue(app(DeliveryService::class)->undoLastDelivery($match, $innings->fresh()));

        $reopened = $innings->fresh();
        $this->assertSame('live', $reopened->status);
        $this->assertSame(9, $reopened->total_wickets);

        // Scoring may continue normally again.
        $this->assertTrue(app(DeliveryService::class)->canRecordDelivery($match, $reopened));
        $this->ball($match, $reopened, ['runs_off_bat' => 4]);
        $this->assertSame(4, $innings->fresh()->total_runs);
    }

    public function test_final_legal_ball_completes_innings_illegal_deliveries_dont_count_and_undo_restores_it(): void
    {
        $match = $this->liveMatchWithSquads(['overs_per_innings' => 1]); // 6 legal balls total
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);

        for ($i = 0; $i < 5; $i++) {
            $this->ball($match, $innings);
        }
        $this->assertSame(5, $innings->fresh()->legal_balls);
        $this->assertSame('live', $innings->fresh()->status);

        // A no-ball here must NOT consume the final legal ball.
        $this->ball($match, $innings, ['extra_type' => 'no_ball', 'extra_amount' => 0]);
        $afterNoBall = $innings->fresh();
        $this->assertSame(5, $afterNoBall->legal_balls);
        $this->assertSame('live', $afterNoBall->status);

        // The genuine 6th legal ball completes the innings.
        $this->ball($match, $innings);
        $completed = $innings->fresh();
        $this->assertSame(6, $completed->legal_balls);
        $this->assertSame('completed', $completed->status);

        // Undoing that exact ball must restore live play with 5 legal balls.
        $this->assertTrue(app(DeliveryService::class)->undoLastDelivery($match, $completed));
        $reopened = $innings->fresh();
        $this->assertSame('live', $reopened->status);
        $this->assertSame(5, $reopened->legal_balls);
    }

    public function test_second_innings_reaching_target_completes_immediately_but_a_tying_score_does_not(): void
    {
        $match = $this->liveMatchWithSquads();
        $first = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);
        $first->update(['status' => 'completed', 'total_runs' => 150, 'total_wickets' => 8]);

        $second = $this->startInnings($match, 2, $match->edition_team_b_id, $match->edition_team_a_id);

        // Reaching exactly 150 (a tie) must NOT complete via target logic.
        $this->ball($match, $second, ['runs_off_bat' => 150]);
        $tied = $second->fresh();
        $this->assertSame(150, $tied->total_runs);
        $this->assertSame('live', $tied->status);

        // The next run reaches the real target (151) and ends it immediately —
        // no need for the over to finish.
        $this->ball($match, $second, ['runs_off_bat' => 1]);
        $won = $second->fresh();
        $this->assertSame(151, $won->total_runs);
        $this->assertSame('completed', $won->status);

        // Undoing the winning run returns the chase to live at 150.
        $this->assertTrue(app(DeliveryService::class)->undoLastDelivery($match, $won));
        $reopened = $second->fresh();
        $this->assertSame('live', $reopened->status);
        $this->assertSame(150, $reopened->total_runs);
    }

    public function test_first_innings_never_completes_from_runs_alone(): void
    {
        $match = $this->liveMatchWithSquads(['overs_per_innings' => 50]); // overs limit far out of reach
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);

        // A huge score, zero wickets, overs nowhere near exhausted — no
        // second-innings target logic exists for innings #1.
        $this->ball($match, $innings, ['runs_off_bat' => 6]);

        $this->assertSame('live', $innings->fresh()->status);
    }

    public function test_manually_completed_innings_is_never_reopened_by_undo(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);

        // A few unremarkable deliveries — no automatic condition holds.
        $this->ball($match, $innings, ['runs_off_bat' => 1]);
        $this->ball($match, $innings, ['runs_off_bat' => 2]);

        $this->assertTrue(app(InningsService::class)->completeInnings($match, $innings->fresh()));
        $manuallyCompleted = $innings->fresh();
        $this->assertSame('completed', $manuallyCompleted->status);
        $this->assertSame(3, $manuallyCompleted->total_runs);

        // Undo must refuse — this was a deliberate manual completion,
        // not one scoring itself decided, and no objective condition
        // currently holds to justify treating it as automatic.
        $this->assertFalse(app(DeliveryService::class)->undoLastDelivery($match, $manuallyCompleted));

        $stillCompleted = $innings->fresh();
        $this->assertSame('completed', $stillCompleted->status);
        $this->assertSame(3, $stillCompleted->total_runs);
        $this->assertSame(2, Delivery::where('innings_id', $stillCompleted->id)->count());
    }

    public function test_full_match_auto_completes_both_innings_without_finalizing_and_finalize_still_succeeds(): void
    {
        $match = $this->liveMatchWithSquads();
        $first = $this->startInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id);

        for ($i = 0; $i < 10; $i++) {
            $this->wicketBall($match, $first);
        }
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('live', $match->fresh()->match_status);

        $this->assertTrue(app(InningsService::class)->startSecondInnings($match->fresh()));
        $second = $match->fresh()->secondInnings;

        // First innings scored 0 (all wickets, no runs) — target is 1.
        $this->ball($match, $second, ['runs_off_bat' => 1]);
        $this->assertSame('completed', $second->fresh()->status);

        // Still no auto-finalization: match remains live, no result written.
        $freshMatch = $match->fresh();
        $this->assertSame('live', $freshMatch->match_status);
        $this->assertNull($freshMatch->match_result);

        // The existing explicit Finalize Result workflow still works normally.
        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertRedirect(route('admin.matches.show', $match));

        $this->assertSame('completed', $match->fresh()->match_status);
        $this->assertSame($match->edition_team_b_id, $match->fresh()->winner_team_id);
    }
}
