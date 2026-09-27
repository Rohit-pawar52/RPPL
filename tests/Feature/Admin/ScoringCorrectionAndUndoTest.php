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
use App\Services\Innings\InningsService;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * S02 rules 42-53 — Universal Undo, the last-3-deliveries quick
 * correction window, and idempotent delivery submission. The pre-
 * existing engine (ScoringTest/StrikeRotationTest/ScoringUpgradeTest/
 * ScoringWorkflowTest) already covers everything else; this file only
 * covers what's new in this pass.
 */
class ScoringCorrectionAndUndoTest extends TestCase
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
    private function freshMatchWithPlayers(): array
    {
        $match = GameMatch::factory()->create(['match_status' => 'live', 'started_at' => now(), 'overs_per_innings' => 20]);
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

    /**
     * @return array{0: GameMatch, 1: Innings, 2: Collection<int, MatchPlayer>, 3: Collection<int, MatchPlayer>}
     */
    private function readyToScoreInnings(): array
    {
        [$match, $battingPlayers, $bowlingPlayers] = $this->freshMatchWithPlayers();

        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));
        $innings = $match->fresh()->firstInnings;

        app(InningsService::class)->setUpOpeningState(
            $match, $innings, $battingPlayers[0]->id, $battingPlayers[1]->id, $bowlingPlayers[0]->id,
        );

        return [$match, $innings->fresh(), $battingPlayers, $bowlingPlayers];
    }

    private function score(GameMatch $match, Innings $innings, array $payload = [])
    {
        return $this->actingAs($this->admin())->post(route('admin.matches.innings.deliveries.store', [$match, $innings]), $payload);
    }

    private function undo(GameMatch $match, Innings $innings)
    {
        return $this->actingAs($this->admin())
            ->delete(route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]), [], ['Accept' => 'application/json']);
    }

    // ----- Universal Undo (rule 42) -----

    public function test_undo_reverses_a_change_strike_event_when_it_is_the_latest_action(): void
    {
        [$match, $innings, $battingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.change-strike', [$match, $innings]), [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'reason' => 'Wrong ends recorded',
        ]);

        $event = ScoringEvent::where('type', ScoringEvent::TYPE_CHANGE_STRIKE)->firstOrFail();
        $stateBeforeUndo = $innings->fresh()->pending_state;
        $this->assertSame($battingPlayers[0]->id, $stateBeforeUndo['striker_id']);

        $response = $this->undo($match, $innings);

        $response->assertOk();
        $this->assertNotNull($event->fresh()->undone_at);

        // Restored to whatever pending_state was immediately before the
        // change-strike event (the natural post-delivery rotation), not
        // a freshly re-derived guess.
        $restored = $innings->fresh()->pending_state;
        $this->assertSame($battingPlayers[1]->id, $restored['striker_id']);
        $this->assertSame($battingPlayers[0]->id, $restored['non_striker_id']);
    }

    public function test_undo_reverses_retired_out_and_restores_wicket_count(): void
    {
        [$match, $innings, $battingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.retire-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[1]->id,
            'type' => 'out',
            'reason' => 'Left the field and did not return',
        ]);

        $this->assertSame(1, $innings->fresh()->total_wickets);

        $response = $this->undo($match, $innings);

        $response->assertOk();
        $this->assertSame(0, $innings->fresh()->total_wickets);
        $this->assertSame('live', $innings->fresh()->status);
    }

    public function test_undo_picks_the_chronologically_latest_action_across_deliveries_and_scoring_events(): void
    {
        [$match, $innings, $battingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        // Latest action is now a delivery (2 runs) recorded AFTER the
        // change-strike below — undo must remove that delivery, not
        // reverse the change-strike underneath it.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.change-strike', [$match, $innings]), [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'reason' => 'Correction',
        ]);
        $this->score($match, $innings, ['runs_off_bat' => 2]);

        $this->assertSame(3, $innings->fresh()->total_runs);

        $this->undo($match, $innings)->assertOk();

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
        $this->assertSame(1, $innings->fresh()->total_runs);
        $this->assertNull(ScoringEvent::where('type', ScoringEvent::TYPE_CHANGE_STRIKE)->first()->undone_at);
    }

    public function test_undo_reverses_penalty_runs_and_removes_the_credited_total(): void
    {
        [$match, $innings] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.penalty-runs', [$match, $innings]), [
            'awarded_team_id' => $innings->batting_team_id,
            'reason' => 'Fielding restriction breach',
        ]);

        $this->assertSame(6, $innings->fresh()->total_runs);

        $this->undo($match, $innings)->assertOk();

        $this->assertSame(1, $innings->fresh()->total_runs);
        $this->assertNotNull(ScoringEvent::where('type', ScoringEvent::TYPE_PENALTY_RUNS)->first()->undone_at);
    }

    public function test_undo_reverses_select_new_batter_even_though_it_normally_has_no_audit_reason(): void
    {
        [$match, $innings, $battingPlayers] = $this->readyToScoreInnings();
        // Wicket on ball 1 — vacates the striker's end.
        $this->score($match, $innings, ['is_wicket' => true, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $battingPlayers[0]->id]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[2]->id,
        ]);

        $this->assertFalse($innings->fresh()->pending_state['requires_replacement']);
        $this->assertDatabaseHas('scoring_events', ['type' => ScoringEvent::TYPE_NEW_BATTER_SELECTED]);

        $this->undo($match, $innings)->assertOk();

        // Back to awaiting a replacement for the same survivor.
        $restored = $innings->fresh()->pending_state;
        $this->assertTrue($restored['requires_replacement']);
        $this->assertSame($battingPlayers[1]->id, $restored['survivor_id']);
    }

    public function test_undo_on_a_manually_completed_innings_is_refused(): void
    {
        [$match, $innings] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.complete', [$match, $innings]), [
            'reason' => 'Rain stopped play',
        ]);

        $response = $this->undo($match, $innings);

        $response->assertStatus(422);
        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
    }

    public function test_undo_with_nothing_to_undo_is_rejected_safely(): void
    {
        [$match, $innings] = $this->readyToScoreInnings();

        $this->undo($match, $innings)->assertStatus(422);
    }

    // ----- Quick correction window (rules 43/44/46) -----

    /**
     * @return array{0: GameMatch, 1: Innings, 2: Collection<int, MatchPlayer>, 3: list<Delivery>}
     */
    private function inningsWithFourDeliveries(): array
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        $this->score($match, $innings, ['runs_off_bat' => 1]);
        $this->score($match, $innings, ['runs_off_bat' => 1]);
        $this->score($match, $innings, ['runs_off_bat' => 1]);
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        $deliveries = Delivery::where('innings_id', $innings->id)->orderBy('delivery_sequence')->get()->all();

        return [$match, $innings->fresh(), $battingPlayers, $deliveries];
    }

    private function correct(GameMatch $match, Innings $innings, Delivery $delivery, array $payload = [])
    {
        return $this->actingAs($this->admin())
            ->patch(route('admin.matches.innings.deliveries.correct', [$match, $innings, $delivery]), $payload, ['Accept' => 'application/json']);
    }

    public function test_latest_delivery_is_correctable_and_totals_rebuild(): void
    {
        [$match, $innings, , $deliveries] = $this->inningsWithFourDeliveries();
        $latest = end($deliveries);

        $response = $this->correct($match, $innings, $latest, ['runs_off_bat' => 4, 'reason' => 'Miscounted, actually a boundary']);

        $response->assertOk();
        $this->assertSame(7, $innings->fresh()->total_runs); // 1+1+1+4
        $this->assertTrue($latest->fresh()->is_edited);
        $this->assertSame('Miscounted, actually a boundary', $latest->fresh()->edit_reason);
        $this->assertDatabaseHas('delivery_corrections', ['delivery_id' => $latest->id]);
    }

    public function test_second_and_third_latest_deliveries_are_correctable(): void
    {
        [$match, $innings, , $deliveries] = $this->inningsWithFourDeliveries();

        // 1 -> 3 keeps the same odd parity (still swaps strike), so
        // neither correction changes what the delivery bowled after it
        // already assumed — both are safe even though neither is latest.
        $this->correct($match, $innings, $deliveries[2], ['runs_off_bat' => 3])->assertOk();
        $this->correct($match, $innings, $deliveries[1], ['runs_off_bat' => 3])->assertOk();

        $this->assertSame(1 + 3 + 3 + 1, $innings->fresh()->total_runs);
    }

    public function test_a_delivery_older_than_the_last_three_is_rejected(): void
    {
        [$match, $innings, , $deliveries] = $this->inningsWithFourDeliveries();

        $response = $this->correct($match, $innings, $deliveries[0], ['runs_off_bat' => 5]);

        $response->assertStatus(422);
        $this->assertSame(1, $deliveries[0]->fresh()->runs_off_bat);
    }

    public function test_wide_or_no_ball_state_cannot_change_on_a_non_latest_delivery(): void
    {
        [$match, $innings, , $deliveries] = $this->inningsWithFourDeliveries();
        $secondLatest = $deliveries[2];

        $response = $this->correct($match, $innings, $secondLatest, ['is_wide' => true]);

        $response->assertStatus(422);
        $this->assertFalse($secondLatest->fresh()->is_wide);
    }

    public function test_turning_a_non_latest_delivery_into_a_wicket_is_rejected_as_unsafe(): void
    {
        [$match, $innings, $battingPlayers, $deliveries] = $this->inningsWithFourDeliveries();
        $secondLatest = $deliveries[2];

        $response = $this->correct($match, $innings, $secondLatest, [
            'is_wicket' => true,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $secondLatest->striker_match_player_id,
        ]);

        // Marking a non-latest ball a wicket would require the already-
        // recorded delivery after it to have prompted a New Batter first
        // — historical state the current architecture cannot safely
        // rebuild, so this must be rejected rather than corrupting state.
        $response->assertStatus(422);
        $this->assertFalse($secondLatest->fresh()->is_wicket);
    }

    public function test_correcting_the_latest_delivery_into_a_wicket_recomputes_pending_state(): void
    {
        [$match, $innings, $battingPlayers, $deliveries] = $this->inningsWithFourDeliveries();
        $latest = end($deliveries);

        $response = $this->correct($match, $innings, $latest, [
            'runs_off_bat' => 0,
            'is_wicket' => true,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $latest->striker_match_player_id,
        ]);

        $response->assertOk();
        $this->assertTrue($innings->fresh()->pending_state['requires_replacement']);
    }

    public function test_correcting_in_the_tenth_wicket_automatically_completes_the_innings(): void
    {
        [$match, $innings, $battingPlayers] = $this->readyToScoreInnings();
        $deliveries = app(DeliveryService::class);

        // 8 wickets via Retired Out — a ScoringEvent, never a Delivery,
        // so this never touches legal_balls/over rotation and needs no
        // bowler reselection along the way (frozen rule 5).
        $nextBatterIndex = 2;
        for ($i = 0; $i < 8; $i++) {
            $state = $deliveries->expectedBattingState($innings->fresh());
            $this->actingAs($this->admin())->post(route('admin.matches.innings.retire-batter', [$match, $innings]), [
                'match_player_id' => $state['striker_id'],
                'type' => 'out',
                'reason' => 'Left the field and did not return',
            ]);
            $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
                'match_player_id' => $battingPlayers[$nextBatterIndex]->id,
            ]);
            $nextBatterIndex++;
        }
        $this->assertSame(8, $innings->fresh()->total_wickets);

        // 9th wicket, a real Delivery (ball 1 of over 0).
        $state = $deliveries->expectedBattingState($innings->fresh());
        $this->score($match, $innings, ['is_wicket' => true, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $state['striker_id']]);
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[10]->id,
        ]);

        // One more delivery (ball 2 of over 0, still well short of the
        // 6-ball over boundary), not a wicket — 9 down and live.
        $this->score($match, $innings, ['runs_off_bat' => 1]);
        $this->assertSame('live', $innings->fresh()->status);
        $this->assertSame(9, $innings->fresh()->total_wickets);

        $latest = Delivery::where('innings_id', $innings->id)->orderByDesc('delivery_sequence')->first();
        $state = $deliveries->expectedBattingState($innings->fresh());

        $response = $this->correct($match, $innings, $latest, [
            'runs_off_bat' => 0,
            'is_wicket' => true,
            'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $state['striker_id'],
        ]);

        $response->assertOk();
        $this->assertSame(10, $innings->fresh()->total_wickets);
        $this->assertSame('completed', $innings->fresh()->status);
        $this->assertSame('automatic', $innings->fresh()->completion_type);
    }

    // ----- Idempotency (rule 51) -----

    public function test_same_idempotency_key_submitted_twice_creates_only_one_delivery(): void
    {
        [$match, $innings] = $this->readyToScoreInnings();

        $this->score($match, $innings, ['runs_off_bat' => 4, 'idempotency_key' => 'quick-tap-abc-123']);
        $this->score($match, $innings, ['runs_off_bat' => 4, 'idempotency_key' => 'quick-tap-abc-123']);

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count());
        $this->assertSame(4, $innings->fresh()->total_runs);
    }

    public function test_a_different_idempotency_key_records_a_genuinely_new_delivery(): void
    {
        [$match, $innings] = $this->readyToScoreInnings();

        $this->score($match, $innings, ['runs_off_bat' => 4, 'idempotency_key' => 'key-one']);
        $this->score($match, $innings, ['runs_off_bat' => 2, 'idempotency_key' => 'key-two']);

        $this->assertSame(2, Delivery::where('innings_id', $innings->id)->count());
        $this->assertSame(6, $innings->fresh()->total_runs);
    }

    public function test_omitting_idempotency_key_behaves_exactly_as_before(): void
    {
        [$match, $innings] = $this->readyToScoreInnings();

        $this->score($match, $innings, ['runs_off_bat' => 1]);
        $this->score($match, $innings, ['runs_off_bat' => 1]);

        $this->assertSame(2, Delivery::where('innings_id', $innings->id)->count());
    }
}
