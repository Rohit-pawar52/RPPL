<?php

namespace Tests\Feature\Admin;

use App\Events\MatchScoreUpdated;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Phase 3.37B2 — proves WHEN MatchScoreUpdated is dispatched, not what
 * it contains (that's Phase 3.37B1's MatchScoreUpdatedTest). Every
 * successful public-state-changing action listed in the phase brief
 * dispatches the event exactly once; every rejected/failed action
 * dispatches it zero times. Only App\Events\MatchScoreUpdated is faked
 * (never Event::fake() globally) so framework-internal events used
 * elsewhere in the request lifecycle are unaffected.
 */
class RealtimeMatchUpdateDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        Event::fake([MatchScoreUpdated::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function assertDispatchedOnceFor(GameMatch $match): void
    {
        Event::assertDispatchedTimes(MatchScoreUpdated::class, 1);
        Event::assertDispatched(MatchScoreUpdated::class, fn (MatchScoreUpdated $event) => $event->matchId === $match->id);
    }

    private function assertNotDispatched(): void
    {
        Event::assertNotDispatched(MatchScoreUpdated::class);
    }

    /**
     * A live match, toss recorded (Team A batting first), 3 selected
     * MatchPlayers per side.
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
            for ($i = 0; $i < 3; $i++) {
                MatchPlayer::factory()->create([
                    'match_id' => $match->id,
                    'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $editionTeamId])->id,
                ]);
            }
        }

        return $match->fresh();
    }

    private function battingPlayers(GameMatch $match, int $editionTeamId)
    {
        return MatchPlayer::query()
            ->where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $editionTeamId))
            ->get();
    }

    private function addInnings(GameMatch $match, int $number, int $battingTeamId, int $bowlingTeamId, string $status, int $runs = 0, int $wickets = 0, int $legalBalls = 0): Innings
    {
        return Innings::create([
            'match_id' => $match->id,
            'innings_number' => $number,
            'batting_team_id' => $battingTeamId,
            'bowling_team_id' => $bowlingTeamId,
            'status' => $status,
            'total_runs' => $runs,
            'total_wickets' => $wickets,
            'legal_balls' => $legalBalls,
        ]);
    }

    // ----- Record delivery -----

    public function test_recording_a_delivery_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');
        $batting = $this->battingPlayers($match, $match->edition_team_a_id);
        $bowling = $this->battingPlayers($match, $match->edition_team_b_id);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
            'striker_match_player_id' => $batting[0]->id,
            'non_striker_match_player_id' => $batting[1]->id,
            'bowler_match_player_id' => $bowling[0]->id,
            'runs_off_bat' => 1,
        ])->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    public function test_a_delivery_that_automatically_completes_the_innings_still_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads(['overs_per_innings' => 1]);
        $innings = $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');
        $batting = $this->battingPlayers($match, $match->edition_team_a_id);
        $bowling = $this->battingPlayers($match, $match->edition_team_b_id);
        $admin = $this->admin();

        // 5 dot balls to reach the 6th (final) legal ball of the 1-over innings.
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($admin)->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
                'striker_match_player_id' => $batting[0]->id,
                'non_striker_match_player_id' => $batting[1]->id,
                'bowler_match_player_id' => $bowling[0]->id,
                'runs_off_bat' => 0,
            ]);
        }

        Event::assertDispatchedTimes(MatchScoreUpdated::class, 5);

        // The 6th ball auto-completes the innings inside the SAME
        // recordDelivery() transaction — this HTTP action must still
        // only add exactly one more dispatch, not two.
        $this->actingAs($admin)->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
            'striker_match_player_id' => $batting[0]->id,
            'non_striker_match_player_id' => $batting[1]->id,
            'bowler_match_player_id' => $bowling[0]->id,
            'runs_off_bat' => 0,
        ])->assertRedirect();

        $this->assertSame('completed', $innings->fresh()->status);
        Event::assertDispatchedTimes(MatchScoreUpdated::class, 6);
    }

    public function test_a_delivery_rejected_by_a_domain_guard_dispatches_no_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');
        $batting = $this->battingPlayers($match, $match->edition_team_a_id);
        $bowling = $this->battingPlayers($match, $match->edition_team_b_id);
        $admin = $this->admin();

        // First ball establishes the pair successfully.
        $this->actingAs($admin)->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
            'striker_match_player_id' => $batting[0]->id,
            'non_striker_match_player_id' => $batting[1]->id,
            'bowler_match_player_id' => $bowling[0]->id,
            'runs_off_bat' => 0,
        ]);
        Event::assertDispatchedTimes(MatchScoreUpdated::class, 1);

        // Second ball deliberately submits the WRONG pair (Phase 3.33's
        // assertExpectedBattingEnds() must throw ValidationException
        // before anything commits) — an existing, real domain-invalid
        // scenario, not artificial DB corruption.
        try {
            $this->actingAs($admin)->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
                'striker_match_player_id' => $batting[2]->id,
                'non_striker_match_player_id' => $batting[1]->id,
                'bowler_match_player_id' => $bowling[0]->id,
                'runs_off_bat' => 0,
            ]);
        } catch (ValidationException) {
            // StoreDeliveryRequest doesn't catch this itself in a raw
            // HTTP test without exception rendering middleware context;
            // either the redirect-with-errors path or the exception
            // itself proves the mutation never committed.
        }

        // Still exactly one — the rejected second ball added nothing.
        Event::assertDispatchedTimes(MatchScoreUpdated::class, 1);
    }

    // ----- Undo -----

    public function test_successful_undo_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');
        $batting = $this->battingPlayers($match, $match->edition_team_a_id);
        $bowling = $this->battingPlayers($match, $match->edition_team_b_id);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), [
            'striker_match_player_id' => $batting[0]->id,
            'non_striker_match_player_id' => $batting[1]->id,
            'bowler_match_player_id' => $bowling[0]->id,
            'runs_off_bat' => 1,
        ]);
        Event::assertDispatchedTimes(MatchScoreUpdated::class, 1);

        $this->actingAs($admin)
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]))
            ->assertRedirect();

        Event::assertDispatchedTimes(MatchScoreUpdated::class, 2);
    }

    public function test_undo_with_nothing_to_undo_dispatches_no_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $innings = $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]))
            ->assertRedirect();

        $this->assertNotDispatched();
    }

    // ----- Innings lifecycle -----

    public function test_starting_first_innings_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    public function test_starting_first_innings_when_already_live_is_rejected_and_dispatches_no_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.first.start', $match))
            ->assertRedirect();

        $this->assertNotDispatched();
    }

    public function test_manually_completing_an_innings_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();
        // legal_balls must be non-zero — InningsService::canCompleteInnings()
        // requires at least one legal delivery before an innings may be
        // manually completed (pre-UAT audit fix).
        $innings = $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live', legalBalls: 1);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.complete', [$match, $innings]))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    public function test_starting_second_innings_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', 120, 5);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.innings.second.start', $match))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    // ----- Match flow -----

    public function test_starting_the_match_dispatches_exactly_one_event(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'toss']);
        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id]);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    public function test_rejected_start_match_dispatches_no_event(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start', $match))
            ->assertRedirect();

        $this->assertNotDispatched();
    }

    public function test_starting_toss_does_not_dispatch_an_event(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id]);
        MatchPlayer::factory()->create(['match_id' => $match->id, 'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_b_id])->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.start-toss', $match))
            ->assertRedirect();

        $this->assertNotDispatched();
    }

    public function test_recording_toss_does_not_dispatch_an_event(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'toss']);

        $this->actingAs($this->admin())
            ->put(route('admin.matches.toss.update', $match), [
                'toss_winner_team_id' => $match->edition_team_a_id,
                'toss_decision' => 'bat',
            ])
            ->assertRedirect();

        $this->assertNotDispatched();
    }

    public function test_cancelling_the_match_dispatches_exactly_one_event(): void
    {
        $match = GameMatch::factory()->create(['match_status' => 'scheduled']);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.cancel', $match))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    public function test_abandoning_the_match_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.abandon', $match))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    // ----- Finalization -----

    public function test_finalizing_the_match_dispatches_exactly_one_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'completed', 150, 6);
        $this->addInnings($match, 2, $match->edition_team_b_id, $match->edition_team_a_id, 'completed', 120, 10);

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertRedirect();

        $this->assertDispatchedOnceFor($match);
    }

    public function test_rejected_finalize_dispatches_no_event(): void
    {
        $match = $this->liveMatchWithSquads();
        $this->addInnings($match, 1, $match->edition_team_a_id, $match->edition_team_b_id, 'live');

        $this->actingAs($this->admin())
            ->post(route('admin.matches.finalize', $match))
            ->assertRedirect();

        $this->assertNotDispatched();
    }
}
