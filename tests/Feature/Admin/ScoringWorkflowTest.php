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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * S02 completion pass — focused coverage for the explicit Start Innings
 * workflow (rule A), server-derived normal ball entry (rule B), New
 * Batter (rule C), New Over Bowler (rule D), and Mid-Over Bowler Change
 * (rule E). See the completion report for the full rule-by-rule
 * breakdown; ScoringTest/StrikeRotationTest/AutomaticInningsCompletion
 * Test/ScoringUpgradeTest already cover the pre-existing engine and the
 * S02/penalty-accounting rules, so this file only covers what's new here.
 */
class ScoringWorkflowTest extends TestCase
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

    /**
     * A live match, toss recorded, exactly 11 selected MatchPlayers per
     * side, no innings yet — ready for the real Start First Innings
     * action.
     *
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
     * A live innings that has already been through explicit setup — the
     * normal "ready to score" starting point for rules B/C/D/E.
     *
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

    // ----- Rule A: explicit Start Innings workflow -----

    public function test_starting_first_innings_does_not_create_a_delivery_and_requires_setup(): void
    {
        [$match, $battingPlayers, $bowlingPlayers] = $this->freshMatchWithPlayers();

        $response = $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));

        $innings = $match->fresh()->firstInnings;
        $this->assertNotNull($innings);
        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
        $response->assertRedirect(route('admin.matches.innings.score', [$match, $innings]));

        $this->assertTrue(app(InningsService::class)->canSetUpOpeningState($match, $innings));
    }

    public function test_first_innings_setup_requires_valid_striker_non_striker_and_bowler(): void
    {
        [$match, $battingPlayers, $bowlingPlayers] = $this->freshMatchWithPlayers();
        $this->actingAs($this->admin())->post(route('admin.matches.innings.first.start', $match));
        $innings = $match->fresh()->firstInnings;

        // Two batters must be different.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.setup', [$match, $innings]), [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ])->assertSessionHasErrors('non_striker_match_player_id');

        // Bowling-side player cannot be the striker.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.setup', [$match, $innings]), [
            'striker_match_player_id' => $bowlingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ]);
        $this->assertNull($innings->fresh()->pending_state);

        // Batting-side player cannot be the bowler.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.setup', [$match, $innings]), [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $battingPlayers[2]->id,
        ]);
        $this->assertNull($innings->fresh()->pending_state);

        // Valid setup succeeds and never creates a Delivery.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.setup', [$match, $innings]), [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ])->assertRedirect();

        $this->assertSame(0, Delivery::where('innings_id', $innings->id)->count());
        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $this->assertSame($battingPlayers[0]->id, $state['striker_id']);
        $this->assertSame($battingPlayers[1]->id, $state['non_striker_id']);
        $this->assertSame($bowlingPlayers[0]->id, $state['bowler_id']);
    }

    public function test_second_innings_requires_its_own_independent_setup(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 0]);
        app(InningsService::class)->completeInnings($match, $innings->fresh(), 'End of innings');

        $response = $this->actingAs($this->admin())->post(route('admin.matches.innings.second.start', $match));
        $second = $match->fresh()->secondInnings;

        $this->assertNotNull($second);
        $this->assertTrue(app(InningsService::class)->canSetUpOpeningState($match, $second));
        $response->assertRedirect(route('admin.matches.innings.score', [$match, $second]));

        // Not ready for ball entry merely because it exists.
        $this->score($match, $second, [
            'striker_match_player_id' => $bowlingPlayers[0]->id,
            'non_striker_match_player_id' => $bowlingPlayers[1]->id,
            'bowler_match_player_id' => $battingPlayers[0]->id,
        ])->assertSessionDoesntHaveErrors(); // explicit fields still work (backward compat)

        // But via the new server-derived (no explicit fields) flow, it
        // must reject until setup runs.
        $secondFresh = Innings::create([
            'match_id' => $match->id, 'innings_number' => 99,
            'batting_team_id' => $match->edition_team_b_id, 'bowling_team_id' => $match->edition_team_a_id,
            'status' => 'live',
        ]);
        $this->expectException(ValidationException::class);
        app(DeliveryService::class)->recordDelivery($match, $secondFresh, ['runs_off_bat' => 0]);
    }

    // ----- Rule B: normal ball entry no longer asks for batters/bowler -----

    public function test_normal_delivery_does_not_require_striker_non_striker_or_bowler_fields(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        $this->score($match, $innings, ['runs_off_bat' => 4])->assertSessionDoesntHaveErrors();

        $delivery = Delivery::sole();
        $this->assertSame($battingPlayers[0]->id, $delivery->striker_match_player_id);
        $this->assertSame($battingPlayers[1]->id, $delivery->non_striker_match_player_id);
        $this->assertSame($bowlingPlayers[0]->id, $delivery->bowler_match_player_id);
    }

    public function test_server_side_batter_eligibility_is_not_weakened_for_explicit_submissions(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        // A bowling-side player still cannot be submitted as striker.
        $this->score($match, $innings, [
            'striker_match_player_id' => $bowlingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0,
        ])->assertSessionHasErrors('striker_match_player_id');
    }

    // ----- Rule C: New Batter -----

    public function test_wicket_puts_innings_into_pending_new_batter_state_without_asking_for_the_whole_pair(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        $this->score($match, $innings, [
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ])->assertSessionDoesntHaveErrors();

        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $this->assertTrue($state['requires_replacement']);
        $this->assertSame($battingPlayers[1]->id, $state['survivor_id']);

        // Normal ball entry is blocked until a new batter is selected.
        $this->score($match, $innings, ['runs_off_bat' => 0])->assertSessionHasErrors('delivery');
    }

    public function test_only_an_eligible_batter_is_accepted_as_the_new_batter(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, [
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]);

        // The surviving batter cannot be reselected as the "new" batter.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[1]->id,
        ])->assertSessionHasErrors('match_player_id');

        // A bowling-side player is not eligible.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $bowlingPlayers[0]->id,
        ])->assertSessionHasErrors('match_player_id');

        // A genuinely eligible batter succeeds and resumes normal ball entry.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $battingPlayers[2]->id,
        ])->assertRedirect();

        $this->assertSame(1, Delivery::where('innings_id', $innings->id)->count()); // no fake Delivery
        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $this->assertFalse($state['requires_replacement']);
        $this->assertContains($battingPlayers[2]->id, [$state['striker_id'], $state['non_striker_id']]);
        $this->assertContains($battingPlayers[1]->id, [$state['striker_id'], $state['non_striker_id']]);

        $this->score($match, $innings, ['runs_off_bat' => 1])->assertSessionDoesntHaveErrors();
    }

    public function test_no_new_batter_prompt_when_the_wicket_ends_the_innings(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        // Fall 9 wickets normally (2 openers + replacements), then the
        // 10th (all-out) must not require a further replacement.
        $batting = $battingPlayers->all();
        $currentStriker = $batting[0]->id;
        $currentNonStriker = $batting[1]->id;
        $nextNewBatterIndex = 2;

        for ($wicket = 1; $wicket <= 10; $wicket++) {
            // A new over needs its bowler selected once (frozen S02
            // completion rule D) — one wicket per ball means an over
            // boundary is crossed every 6 wickets.
            $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
            if ($state['awaiting_new_over_bowler']) {
                $previousBowlerId = app(DeliveryService::class)->bowlerOfPreviousOver($innings->fresh());
                $nextBowlerId = $bowlingPlayers->firstWhere('id', '!=', $previousBowlerId)->id;
                $this->actingAs($this->admin())->post(route('admin.matches.innings.select-over-bowler', [$match, $innings]), [
                    'bowler_match_player_id' => $nextBowlerId,
                ]);
            }

            $this->score($match, $innings, [
                'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled',
                'dismissed_match_player_id' => $currentStriker,
            ]);

            if ($wicket === 10) {
                break;
            }

            $newBatterId = $batting[$nextNewBatterIndex]->id;
            $nextNewBatterIndex++;
            $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
                'match_player_id' => $newBatterId,
            ]);
            $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
            $currentStriker = $state['striker_id'];
            $currentNonStriker = $state['non_striker_id'];
        }

        $fresh = $innings->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(10, $fresh->total_wickets);

        // The innings is over — no further batter is ever asked for.
        // canRecordDelivery() (and the Blade view it gates) is what
        // actually suppresses the prompt; selectNewBatter() independently
        // refuses to act on a no-longer-live innings as defense-in-depth.
        $this->assertFalse(app(DeliveryService::class)->canRecordDelivery($match, $fresh));
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-new-batter', [$match, $innings]), [
            'match_player_id' => $batting[0]->id,
        ])->assertSessionHasErrors();
    }

    // ----- Rule D: New Over Bowler -----

    public function test_new_over_requires_bowler_selection_and_blocks_the_previous_bowler(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        for ($i = 0; $i < 6; $i++) {
            $this->score($match, $innings, ['runs_off_bat' => 0]);
        }

        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $this->assertTrue($state['awaiting_new_over_bowler']);

        // Normal ball entry is blocked until a bowler is selected.
        $this->score($match, $innings, ['runs_off_bat' => 0])->assertSessionHasErrors('delivery');

        // The previous over's bowler is hard-blocked.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-over-bowler', [$match, $innings]), [
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ])->assertSessionHasErrors('bowler_match_player_id');

        // A different bowler succeeds and persists for the whole over.
        $this->actingAs($this->admin())->post(route('admin.matches.innings.select-over-bowler', [$match, $innings]), [
            'bowler_match_player_id' => $bowlingPlayers[1]->id,
        ])->assertRedirect();

        $this->score($match, $innings, ['runs_off_bat' => 0]);
        $this->score($match, $innings, ['runs_off_bat' => 0]);

        $recent = Delivery::where('innings_id', $innings->id)->orderByDesc('delivery_sequence')->limit(2)->get();
        $this->assertTrue($recent->every(fn ($d) => $d->bowler_match_player_id === $bowlingPlayers[1]->id));
    }

    public function test_wide_and_no_ball_do_not_reset_the_current_over_bowler(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();

        $this->score($match, $innings, ['is_wide' => 1]);
        $this->score($match, $innings, ['is_no_ball' => 1]);

        $state = app(DeliveryService::class)->expectedBattingState($innings->fresh());
        $this->assertFalse($state['awaiting_new_over_bowler']);
        $this->assertSame($bowlingPlayers[0]->id, $state['bowler_id']);

        $this->score($match, $innings, ['runs_off_bat' => 0])->assertSessionDoesntHaveErrors();
        $this->assertSame($bowlingPlayers[0]->id, Delivery::latest('delivery_sequence')->first()->bowler_match_player_id);
    }

    // ----- Rule E: Mid-Over Bowler Change -----

    public function test_mid_over_bowler_change_requires_a_reason_and_preserves_original_attribution(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 0]);
        $firstDeliveryId = Delivery::sole()->id;

        $this->actingAs($this->admin())->post(route('admin.matches.innings.change-bowler', [$match, $innings]), [
            'bowler_match_player_id' => $bowlingPlayers[1]->id,
        ])->assertSessionHasErrors('reason');

        $this->actingAs($this->admin())->post(route('admin.matches.innings.change-bowler', [$match, $innings]), [
            'bowler_match_player_id' => $bowlingPlayers[1]->id,
            'reason' => 'Injury',
        ])->assertRedirect();

        // Already-bowled delivery keeps its original bowler.
        $this->assertSame($bowlingPlayers[0]->id, Delivery::find($firstDeliveryId)->bowler_match_player_id);

        // Subsequent deliveries use the replacement.
        $this->score($match, $innings, ['runs_off_bat' => 0]);
        $this->assertSame($bowlingPlayers[1]->id, Delivery::latest('delivery_sequence')->first()->bowler_match_player_id);

        $event = ScoringEvent::where('type', ScoringEvent::TYPE_BOWLER_CHANGE_MID_OVER)->sole();
        $this->assertSame('Injury', $event->reason);
        $this->assertSame($bowlingPlayers[0]->id, $event->payload['old_bowler_match_player_id']);
        $this->assertSame($bowlingPlayers[1]->id, $event->payload['new_bowler_match_player_id']);
    }

    public function test_mid_over_bowler_change_applies_existing_playing_xi_eligibility(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->readyToScoreInnings();
        $this->score($match, $innings, ['runs_off_bat' => 0]);

        $this->actingAs($this->admin())->post(route('admin.matches.innings.change-bowler', [$match, $innings]), [
            'bowler_match_player_id' => $battingPlayers[0]->id,
            'reason' => 'Injury',
        ])->assertSessionHasErrors('bowler_match_player_id');
    }
}
