<?php

namespace Tests\Feature\Scoring;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Innings\InningsService;
use App\Services\Scoring\DeliveryService;
use App\Services\Scoring\LiveScoringStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * S02 rules 54-61 — the derived scorer-state figures (This/Previous
 * Over, CRR, chase info, current partnership, Last Wicket). Everything
 * here is computed fresh from Delivery/ScoringEvent rows, never a second
 * stored source of truth — these tests exist to pin down the exact
 * arithmetic/labels, not to re-test scoring rules already covered
 * elsewhere.
 */
class LiveScoringStateServiceTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    /**
     * @return array{0: GameMatch, 1: Collection<int, MatchPlayer>, 2: Collection<int, MatchPlayer>}
     */
    private function freshMatchWithPlayers(int $oversPerInnings = 20): array
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now(), 'overs_per_innings' => $oversPerInnings]);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $battingPlayers = collect(range(1, 11))->map(fn () => MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]));

        $bowlingPlayers = collect(range(1, 11))->map(fn () => MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id,
        ]));

        return [$match->fresh(), $battingPlayers, $bowlingPlayers];
    }

    private function score(GameMatch $match, $innings, array $payload = [])
    {
        return $this->actingAs($this->admin())->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), $payload);
    }

    public function test_crr_and_over_strips_reflect_deliveries_bowled_this_over_and_last(): void
    {
        [$match, $battingPlayers, $bowlingPlayers] = $this->freshMatchWithPlayers();
        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));
        $innings = $match->fresh()->firstInnings;
        app(InningsService::class)->setUpOpeningState($match, $innings, $battingPlayers[0]->id, $battingPlayers[1]->id, $bowlingPlayers[0]->id);

        // Over 0: 4, 1, 1, 1, 1, 1 = 9 runs, 6 legal balls.
        foreach ([4, 1, 1, 1, 1, 1] as $run) {
            $this->score($match, $innings, ['runs_off_bat' => $run]);
        }
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-over-bowler', [$match, $innings]), [
            'bowler_match_player_id' => $bowlingPlayers[1]->id,
        ]);
        // Over 1: 2 balls so far.
        $this->score($match, $innings, ['runs_off_bat' => 2]);
        $this->score($match, $innings, ['is_wicket' => true, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $this->currentStriker($match, $innings)]);
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[2]->id,
        ]);

        $state = app(LiveScoringStateService::class)->getState($match, $innings->fresh());

        // 11 runs off 8 legal balls -> 11*6/8 = 8.25.
        $this->assertSame(8.25, $state['innings']['crr']);

        $this->assertSame(2, $state['this_over']['over_number']);
        $this->assertCount(2, $state['this_over']['balls']);
        $this->assertSame('W', $state['this_over']['balls'][1]['label']);
        $this->assertTrue($state['this_over']['balls'][1]['is_wicket']);

        $this->assertSame(1, $state['previous_over']['over_number']);
        $this->assertCount(6, $state['previous_over']['balls']);

        // Partnership reset by the wicket ball itself — no ball has been
        // bowled to the new pair yet.
        $this->assertSame(['runs' => 0, 'balls' => 0], $state['partnership']);

        $this->assertNotNull($state['last_wicket']);
        $this->assertSame(11, $state['last_wicket']['team_score']);

        // Latest 3 deliveries are correctable; the over-2 wicket ball is
        // among them and must be flagged as such in the over strip.
        $this->assertTrue($state['this_over']['balls'][1]['is_correctable']);
    }

    public function test_chase_info_is_computed_for_the_second_innings_only(): void
    {
        [$match, $battingPlayers, $bowlingPlayers] = $this->freshMatchWithPlayers(oversPerInnings: 5);
        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));
        $first = $match->fresh()->firstInnings;
        app(InningsService::class)->setUpOpeningState($match, $first, $battingPlayers[0]->id, $battingPlayers[1]->id, $bowlingPlayers[0]->id);
        $this->bowlFullInnings($match, $first, $bowlingPlayers);

        $firstState = app(LiveScoringStateService::class)->getState($match, $first->fresh());
        $this->assertNull($firstState['chase']);

        app(InningsService::class)->startSecondInnings($match->fresh());
        $second = $match->fresh()->secondInnings;
        app(InningsService::class)->setUpOpeningState($match, $second, $bowlingPlayers[0]->id, $bowlingPlayers[1]->id, $battingPlayers[0]->id);

        $this->score($match, $second, ['runs_off_bat' => 4]);

        $secondState = app(LiveScoringStateService::class)->getState($match, $second->fresh());
        $firstTotal = $first->fresh()->total_runs;

        $this->assertSame($firstTotal + 1, $secondState['chase']['target']);
        $this->assertSame($firstTotal + 1 - 4, $secondState['chase']['runs_needed']);
        $this->assertSame((5 * 6) - 1, $secondState['chase']['balls_remaining']);
    }

    private function currentStriker(GameMatch $match, $innings): int
    {
        return app(DeliveryService::class)->expectedBattingState($innings->fresh())['striker_id'];
    }

    /**
     * Bowls single after single until the innings ends automatically
     * (overs exhausted), rotating between two bowlers at every over
     * boundary — the same "no bowler twice in a row" rule the real
     * scoring engine enforces (frozen rule 9).
     *
     * @param  Collection<int, MatchPlayer>  $bowlingPlayers
     */
    private function bowlFullInnings(GameMatch $match, $innings, Collection $bowlingPlayers): void
    {
        $deliveries = app(DeliveryService::class);
        // Starts at 1, not 0: the opening bowler (index 0, set by
        // setUpOpeningState()) already bowled over 0, so the first over
        // boundary must pick a DIFFERENT bowler.
        $bowlerIndex = 1;

        while ($deliveries->canRecordDelivery($match, $innings->fresh())) {
            $state = $deliveries->expectedBattingState($innings->fresh());

            if ($state['awaiting_new_over_bowler']) {
                $this->actingAs($this->admin())->post(route('admin.matches.innings.select-over-bowler', [$match, $innings]), [
                    'bowler_match_player_id' => $bowlingPlayers[$bowlerIndex % $bowlingPlayers->count()]->id,
                ]);
                $bowlerIndex++;
            }

            $this->score($match, $innings, ['runs_off_bat' => 1]);
        }
    }
}
