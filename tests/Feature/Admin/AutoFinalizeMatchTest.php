<?php

namespace Tests\Feature\Admin;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\GameMatch\MatchResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A match finalizes by itself the moment the scorer's last ball settles it, and the scorer (not only the admin) can
 * reopen a finalized match with a reason, with no time limit.
 */
class AutoFinalizeMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorer = User::factory()->create(['role_id' => Role::create(['name' => 'Scorer', 'slug' => 'scorer'])->id]);
    }

    /**
     * A live match whose first innings (team A) is done on $first runs, and the chase (team B, live, nothing
     * scored yet) to beat it.
     *
     * @return array{0: GameMatch, 1: Innings}
     */
    private function chase(int $first, int $overs = 20): array
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now(), 'overs_per_innings' => $overs]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        foreach ([$match->edition_team_a_id, $match->edition_team_b_id] as $editionTeamId) {
            for ($i = 0; $i < 11; $i++) {
                MatchPlayer::factory()->create([
                    'match_id' => $match->id,
                    'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
                ]);
            }
        }

        Innings::create([
            'match_id' => $match->id, 'innings_number' => 1,
            'batting_team_id' => $match->edition_team_a_id, 'bowling_team_id' => $match->edition_team_b_id,
            'status' => 'completed', 'completion_type' => 'automatic', 'total_runs' => $first, 'total_wickets' => 8, 'legal_balls' => $overs * 6,
        ]);
        $second = Innings::create([
            'match_id' => $match->id, 'innings_number' => 2,
            'batting_team_id' => $match->edition_team_b_id, 'bowling_team_id' => $match->edition_team_a_id,
            'status' => 'live',
        ]);

        return [$match->fresh(), $second];
    }

    /** Scores one ball of the chase over HTTP, as the scorer's screen does. */
    private function ball(GameMatch $match, Innings $innings, int $runs): void
    {
        $batters = MatchPlayer::query()->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $innings->batting_team_id))->orderBy('id')->get();
        $bowler = MatchPlayer::query()->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $innings->bowling_team_id))->orderBy('id')->first();

        $this->actingAs($this->scorer)
            ->postJson(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
                'striker_match_player_id' => $batters[0]->id,
                'non_striker_match_player_id' => $batters[1]->id,
                'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => $runs,
            ])
            ->assertOk();
    }

    public function test_the_ball_that_wins_the_chase_finalizes_the_match_by_itself(): void
    {
        [$match, $second] = $this->chase(first: 7);

        $this->ball($match, $second, 4);
        $this->assertSame('live', $match->fresh()->match_status); // 4 of 8 needed: still on

        $this->ball($match, $second, 4);

        $done = $match->fresh();
        $this->assertSame('completed', $done->match_status);
        $this->assertSame($match->edition_team_b_id, $done->winner_team_id);
        $this->assertSame('wickets', $done->win_margin_type);
        $this->assertNotNull($done->completed_at);
    }

    public function test_a_tie_is_never_decided_by_itself_because_it_needs_a_super_over_winner(): void
    {
        // A one-over match: 6 chasing 6, a six then five dot balls: the innings is over, level, match still open.
        [$match, $second] = $this->chase(first: 6, overs: 1);

        foreach ([6, 0, 0, 0, 0, 0] as $runs) {
            $this->ball($match, $second, $runs);
        }

        $this->assertSame('completed', $second->fresh()->status);
        $this->assertSame('live', $match->fresh()->match_status);
        $this->assertNull($match->fresh()->match_result);
    }

    public function test_a_second_innings_ended_by_hand_is_left_for_a_person_to_finalize(): void
    {
        [$match, $second] = $this->chase(first: 90);
        $second->update(['status' => 'completed', 'completion_type' => 'manual', 'completion_reason' => 'Rain stopped play']);

        $this->assertFalse(app(MatchResultService::class)->finalizeIfDecided($match));
        $this->assertSame('live', $match->fresh()->match_status);
        // ...while the Finalize button still can.
        $this->assertTrue(app(MatchResultService::class)->canFinalize($match->fresh()));
    }

    public function test_the_scorer_can_reopen_a_match_that_finalized_by_itself_and_fix_the_last_ball(): void
    {
        [$match, $second] = $this->chase(first: 5);
        $this->ball($match, $second, 6); // a mistaken six wins it
        $this->assertSame('completed', $match->fresh()->match_status);

        $this->actingAs($this->scorer)->post(route('admin.matches.reopen', $match))->assertSessionHasErrors('reason');
        $this->actingAs($this->scorer)
            ->post(route('admin.matches.reopen', $match), ['reason' => 'That was a dot ball'])
            ->assertRedirect(route('admin.matches.show', $match));

        $reopened = $match->fresh();
        $this->assertSame('live', $reopened->match_status);
        $this->assertNull($reopened->match_result);

        // The last ball can now be undone: the chase is live again and the score is back to nothing.
        $this->actingAs($this->scorer)
            ->deleteJson(route('admin.matches.innings.deliveries.undo-latest', [$match, $second]))
            ->assertOk();
        $this->assertSame('live', $second->fresh()->status);
        $this->assertSame(0, $second->fresh()->total_runs);
    }
}
