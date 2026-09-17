<?php

namespace Tests\Feature\Admin;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Role;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScoringTest extends TestCase
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
     * A live match (toss already recorded, Team A batting) with 3
     * selected MatchPlayers per side and a live Innings #1 ready to be
     * scored.
     *
     * @return array{0: GameMatch, 1: Innings, 2: Collection<int, MatchPlayer>, 3: Collection<int, MatchPlayer>}
     */
    private function matchWithLiveInnings(array $matchAttributes = [], array $inningsAttributes = []): array
    {
        $match = GameMatch::factory()->create(array_merge([
            'match_status' => 'live',
            'started_at' => now(),
            'overs_per_innings' => 20,
        ], $matchAttributes));

        $match->update(['toss_winner_team_id' => $match->edition_team_a_id, 'toss_decision' => 'bat']);

        $battingPlayers = collect(range(1, 3))->map(fn () => MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $match->edition_team_a_id])->id,
        ]));

        $bowlingPlayers = collect(range(1, 3))->map(fn () => MatchPlayer::factory()->create([
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

    /**
     * @param  Collection<int, MatchPlayer>  $battingPlayers
     * @param  Collection<int, MatchPlayer>  $bowlingPlayers
     */
    private function validPayload(Collection $battingPlayers, Collection $bowlingPlayers, array $overrides = []): array
    {
        return array_merge([
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 1,
        ], $overrides);
    }

    private function score(User $user, GameMatch $match, Innings $innings, array $payload)
    {
        return $this->actingAs($user)
            ->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), $payload);
    }

    // ----- Authorization -----

    public function test_guest_is_blocked(): void
    {
        [$match, $innings] = $this->matchWithLiveInnings();

        $this->get(route('admin.matches.innings.score', [$match, $innings]))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->actingAs($this->admin())->get(route('admin.matches.innings.score', [$match, $innings]))->assertOk();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertRedirect(route('admin.matches.innings.score', [$match, $innings]));

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_scorer_can_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->actingAs($this->scorer())->get(route('admin.matches.innings.score', [$match, $innings]))->assertOk();

        $this->score($this->scorer(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertRedirect(route('admin.matches.innings.score', [$match, $innings]));

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_scorer_still_cannot_edit_fixture_through_game_match_crud(): void
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
    }

    // ----- Score cache rebuild: run/extra combinations -----

    public function test_recording_zero_run_legal_ball(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 0]));

        $delivery = Delivery::sole();
        $this->assertSame(0, $delivery->total_runs);
        $this->assertTrue($delivery->is_legal_delivery);

        $innings->refresh();
        $this->assertSame(0, $innings->total_runs);
        $this->assertSame(0, $innings->extras);
        $this->assertSame(1, $innings->legal_balls);
        $this->assertSame(0, $innings->total_wickets);
    }

    public function test_recording_single_run(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 1]));

        $innings->refresh();
        $this->assertSame(1, $innings->total_runs);
        $this->assertSame(1, $innings->legal_balls);
        $this->assertSame(0, $innings->extras);
    }

    public function test_recording_four(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 4]));

        $delivery = Delivery::sole();
        $this->assertSame(4, $delivery->runs_off_bat);
        $this->assertSame(4, $delivery->total_runs);

        $this->assertSame(4, $innings->fresh()->total_runs);
    }

    public function test_recording_six(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 6]));

        $this->assertSame(6, $innings->fresh()->total_runs);
        $this->assertSame(1, $innings->fresh()->legal_balls);
    }

    public function test_recording_wide(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'extra_type' => 'wide',
            'extra_amount' => 1,
        ]));

        $delivery = Delivery::sole();
        $this->assertSame(1, $delivery->wide_runs);
        $this->assertSame(0, $delivery->runs_off_bat);
        $this->assertSame(1, $delivery->total_runs);
        $this->assertFalse($delivery->is_legal_delivery);

        $innings->refresh();
        $this->assertSame(1, $innings->total_runs);
        $this->assertSame(1, $innings->extras);
        $this->assertSame(0, $innings->legal_balls);
    }

    public function test_recording_multiple_wides(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1,
        ]));
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 5,
        ]));

        $innings->refresh();
        $this->assertSame(6, $innings->total_runs);
        $this->assertSame(6, $innings->extras);
        $this->assertSame(0, $innings->legal_balls);
        $this->assertSame(2, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_recording_no_ball(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'extra_type' => 'no_ball', 'extra_amount' => 1,
        ]));

        $delivery = Delivery::sole();
        $this->assertSame(1, $delivery->no_ball_runs);
        $this->assertSame(1, $delivery->total_runs);
        $this->assertFalse($delivery->is_legal_delivery);

        $innings->refresh();
        $this->assertSame(1, $innings->total_runs);
        $this->assertSame(1, $innings->extras);
        $this->assertSame(0, $innings->legal_balls);
    }

    public function test_recording_no_ball_with_bat_runs(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 4, 'extra_type' => 'no_ball', 'extra_amount' => 1,
        ]));

        $delivery = Delivery::sole();
        $this->assertSame(4, $delivery->runs_off_bat);
        $this->assertSame(1, $delivery->no_ball_runs);
        $this->assertSame(5, $delivery->total_runs);

        $innings->refresh();
        $this->assertSame(5, $innings->total_runs);
        $this->assertSame(1, $innings->extras);
        $this->assertSame(0, $innings->legal_balls);
    }

    public function test_recording_bye(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'extra_type' => 'bye', 'extra_amount' => 2,
        ]));

        $delivery = Delivery::sole();
        $this->assertSame(2, $delivery->bye_runs);
        $this->assertSame(0, $delivery->runs_off_bat);
        $this->assertTrue($delivery->is_legal_delivery);

        $innings->refresh();
        $this->assertSame(2, $innings->total_runs);
        $this->assertSame(2, $innings->extras);
        $this->assertSame(1, $innings->legal_balls);
    }

    public function test_recording_leg_bye(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'extra_type' => 'leg_bye', 'extra_amount' => 1,
        ]));

        $delivery = Delivery::sole();
        $this->assertSame(1, $delivery->leg_bye_runs);
        $this->assertTrue($delivery->is_legal_delivery);

        $innings->refresh();
        $this->assertSame(1, $innings->total_runs);
        $this->assertSame(1, $innings->extras);
        $this->assertSame(1, $innings->legal_balls);
    }

    public function test_recording_wicket(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'is_wicket' => 1,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]))->assertRedirect(route('admin.matches.innings.score', [$match, $innings]));

        $delivery = Delivery::sole();
        $this->assertTrue($delivery->is_wicket);
        $this->assertSame('bowled', $delivery->wicket_type);
        $this->assertNull($delivery->fielder_match_player_id);

        $innings->refresh();
        $this->assertSame(1, $innings->total_wickets);
        $this->assertSame(1, $innings->legal_balls);
    }

    // ----- Undo -----

    public function test_undo_after_single_run_zeroes_totals(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 1]));

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]))
            ->assertRedirect(route('admin.matches.innings.score', [$match, $innings]));

        $innings->refresh();
        $this->assertSame(0, $innings->total_runs);
        $this->assertSame(0, $innings->legal_balls);
        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_undo_after_four_zeroes_totals(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 4]));

        $this->actingAs($this->admin())->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]));

        $innings->refresh();
        $this->assertSame(0, $innings->total_runs);
        $this->assertSame(0, $innings->legal_balls);
    }

    public function test_undo_wide_after_legal_ball_keeps_legal_balls_unchanged(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 1]));
        // The single is an odd run and rotates strike (Phase 3.33).
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1,
        ]));

        $this->actingAs($this->admin())->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]));

        $innings->refresh();
        $this->assertSame(1, $innings->legal_balls);
        $this->assertSame(1, $innings->total_runs);
        $this->assertSame(0, $innings->extras);
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_undo_legal_ball_after_wide_resets_legal_balls(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1,
        ]));
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 1]));

        $this->actingAs($this->admin())->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]));

        $innings->refresh();
        $this->assertSame(0, $innings->legal_balls);
        $this->assertSame(1, $innings->extras);
        $this->assertSame(1, $innings->total_runs);
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_undo_wicket_decreases_wicket_count(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]));

        $this->actingAs($this->admin())->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]));

        $this->assertSame(0, $innings->fresh()->total_wickets);
    }

    public function test_undo_only_removes_latest_of_multiple(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        // The single is an odd run and rotates strike (Phase 3.33); the
        // 4 and 6 are even, so the pair stays put after that.
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 1]));
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'runs_off_bat' => 4,
        ]));
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'runs_off_bat' => 6,
        ]));

        $this->actingAs($this->admin())->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]));

        $this->assertSame(2, Delivery::where('innings_id', $innings->id)->count());
        $this->assertSame(5, $innings->fresh()->total_runs); // 1 + 4, the "6" delivery was undone
        $this->assertFalse(Delivery::where('innings_id', $innings->id)->where('total_runs', 6)->exists());
    }

    public function test_cannot_undo_from_completed_innings(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 1]));
        $innings->update(['status' => 'completed']);

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]))
            ->assertSessionHas('error');

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_cannot_undo_through_wrong_match_or_innings_route(): void
    {
        [$matchA, $inningsA, $battingA, $bowlingA] = $this->matchWithLiveInnings();
        [$matchB, $inningsB, $battingB, $bowlingB] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $matchB, $inningsB, $this->validPayload($battingB, $bowlingB, ['runs_off_bat' => 4]));

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$matchA, $inningsB]))
            ->assertNotFound();

        $this->assertSame(1, Delivery::where('innings_id', $inningsB->id)->count());
    }

    public function test_no_delivery_to_undo_is_rejected_safely(): void
    {
        [$match, $innings] = $this->matchWithLiveInnings();

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]))
            ->assertSessionHas('error');
    }

    // ----- Participant validation -----

    public function test_batting_player_can_be_striker(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_bowling_player_cannot_be_striker(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'striker_match_player_id' => $bowlingPlayers[1]->id,
        ]))->assertSessionHasErrors('striker_match_player_id');
    }

    public function test_same_striker_and_non_striker_rejected(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'non_striker_match_player_id' => $battingPlayers[0]->id,
        ]))->assertSessionHasErrors('non_striker_match_player_id');
    }

    public function test_bowling_side_player_can_be_bowler(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_batting_side_player_cannot_be_bowler(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'bowler_match_player_id' => $battingPlayers[2]->id,
        ]))->assertSessionHasErrors('bowler_match_player_id');
    }

    public function test_player_from_another_match_rejected(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        [, , $otherBattingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'striker_match_player_id' => $otherBattingPlayers[0]->id,
        ]))->assertSessionHasErrors('striker_match_player_id');
    }

    public function test_inactive_master_player_remains_eligible(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $player = $battingPlayers[0]->teamPlayer->playerRegistration->player;
        $player->update(['is_active' => false]);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'striker_match_player_id' => $battingPlayers[0]->id,
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    // ----- Lifecycle -----

    public function test_scheduled_match_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['match_status' => 'scheduled', 'started_at' => null]);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_toss_match_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['match_status' => 'toss', 'started_at' => null]);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_live_match_with_live_innings_can_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertRedirect(route('admin.matches.innings.score', [$match, $innings]));

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_completed_innings_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings([], ['status' => 'completed']);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_abandoned_innings_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings([], ['status' => 'abandoned']);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_completed_match_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['match_status' => 'completed']);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_abandoned_match_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['match_status' => 'abandoned']);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_cancelled_match_cannot_score(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['match_status' => 'cancelled']);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers))
            ->assertSessionHas('error');

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_overs_limit_prevents_additional_delivery(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['overs_per_innings' => 1]);

        for ($i = 0; $i < 6; $i++) {
            $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 0]));
        }

        $this->assertSame(6, $innings->fresh()->legal_balls);

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 0]))
            ->assertSessionHas('error');

        $this->assertSame(6, Delivery::where('innings_id', $innings->id)->count());
    }

    // ----- Wicket rules -----

    public function test_caught_requires_fielder(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'caught', 'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]))->assertSessionHasErrors('fielder_match_player_id');
    }

    public function test_caught_with_valid_fielder_succeeds(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'is_wicket' => 1,
            'wicket_type' => 'caught',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $innings->fresh()->total_wickets);
    }

    public function test_bowled_forbids_fielder(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'is_wicket' => 1,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]))->assertSessionHasErrors('fielder_match_player_id');
    }

    public function test_dismissed_player_must_be_striker_or_non_striker(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'is_wicket' => 1,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[2]->id, // neither striker nor non-striker
        ]))->assertSessionHasErrors('dismissed_match_player_id');
    }

    public function test_bowled_on_a_wide_is_rejected(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'extra_type' => 'wide',
            'extra_amount' => 1,
            'is_wicket' => 1,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]))->assertSessionHasErrors('wicket_type');
    }

    public function test_caught_on_a_no_ball_is_rejected(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'extra_type' => 'no_ball',
            'extra_amount' => 1,
            'is_wicket' => 1,
            'wicket_type' => 'caught',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]))->assertSessionHasErrors('wicket_type');
    }

    public function test_run_out_valid_on_a_wide(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 0,
            'extra_type' => 'wide',
            'extra_amount' => 1,
            'is_wicket' => 1,
            'wicket_type' => 'run_out',
            'dismissed_match_player_id' => $battingPlayers[1]->id,
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame(1, $innings->fresh()->total_wickets);
    }

    public function test_non_wicket_delivery_cannot_carry_dismissal_metadata(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, [
            'runs_off_bat' => 1,
            // is_wicket intentionally omitted/false, but dismissal fields
            // are still (mistakenly, or maliciously) present.
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]));

        $delivery = Delivery::sole();
        $this->assertFalse($delivery->is_wicket);
        $this->assertNull($delivery->wicket_type);
        $this->assertNull($delivery->dismissed_match_player_id);
    }

    // ----- Route ownership -----

    public function test_scoring_page_rejects_innings_from_another_match(): void
    {
        [$matchA] = $this->matchWithLiveInnings();
        [, $inningsB] = $this->matchWithLiveInnings();

        $this->actingAs($this->admin())
            ->get(route('admin.matches.innings.score', [$matchA, $inningsB]))
            ->assertNotFound();
    }

    /**
     * $inningsB's batting/bowling teams belong to matchB, never matchA
     * — so no MatchPlayer can simultaneously satisfy "belongs to
     * $matchA" (participant eligibility) AND "belongs to $inningsB's
     * team" at once. StoreDeliveryRequest's own closures already make
     * this combination unsatisfiable (302 with validation errors,
     * before the controller's explicit 404 guard is even reached) —
     * this asserts that cross-match protection, and that the
     * controller's abort_unless() is still exercised (and verified
     * separately) for routes with no body to validate, e.g. show().
     */
    public function test_store_rejects_innings_from_another_match(): void
    {
        [$matchA] = $this->matchWithLiveInnings();
        [, $inningsB, $battingB, $bowlingB] = $this->matchWithLiveInnings();

        $this->score($this->admin(), $matchA, $inningsB, $this->validPayload($battingB, $bowlingB))
            ->assertSessionHasErrors(['striker_match_player_id', 'non_striker_match_player_id', 'bowler_match_player_id']);

        $this->assertSame(0, Delivery::where('innings_id', $inningsB->id)->count());
    }

    public function test_undo_latest_rejects_innings_from_another_match_via_404(): void
    {
        [$matchA] = $this->matchWithLiveInnings();
        [, $inningsB, $battingB, $bowlingB] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $inningsB->match, $inningsB, $this->validPayload($battingB, $bowlingB));

        $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$matchA, $inningsB]))
            ->assertNotFound();

        $this->assertSame(1, Delivery::where('innings_id', $inningsB->id)->count());
    }

    // ----- UI -----

    public function test_scoring_page_shows_delivery_form_when_eligible(): void
    {
        [$match, $innings] = $this->matchWithLiveInnings();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.innings.score', [$match, $innings]));

        $response->assertOk();
        $response->assertSee('Record Delivery');
    }

    public function test_scoring_page_hides_form_when_over_limit_reached(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings(['overs_per_innings' => 1]);
        for ($i = 0; $i < 6; $i++) {
            $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 0]));
        }

        $response = $this->actingAs($this->admin())->get(route('admin.matches.innings.score', [$match, $innings]));

        $response->assertOk();
        $response->assertDontSee('Record Delivery');
        $response->assertSee('Over limit reached');
    }

    public function test_scoring_page_shows_recent_deliveries_and_undo_button(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->score($this->admin(), $match, $innings, $this->validPayload($battingPlayers, $bowlingPlayers, ['runs_off_bat' => 4]));

        $response = $this->actingAs($this->admin())->get(route('admin.matches.innings.score', [$match, $innings]));

        $response->assertOk();
        $response->assertSee('Undo Last Delivery');
        $response->assertSee($battingPlayers[0]->teamPlayer->playerRegistration->player->name);
    }

    public function test_score_innings_link_visible_on_match_show_page(): void
    {
        [$match, $innings] = $this->matchWithLiveInnings();

        $response = $this->actingAs($this->admin())->get(route('admin.matches.show', $match));

        $response->assertOk();
        $response->assertSee(route('admin.matches.innings.score', [$match, $innings]), false);
    }
}
