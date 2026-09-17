<?php

namespace Tests\Feature\Public;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveMatchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: GameMatch, 1: Innings, 2: MatchPlayer, 3: MatchPlayer, 4: MatchPlayer, 5: Player, 6: Player}
     */
    private function matchWithInningsAndPlayers(array $matchAttributes = [], array $inningsAttributes = []): array
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $match = GameMatch::factory()->create(array_merge([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'live',
            'started_at' => now(),
        ], $matchAttributes));
        $match->update(['toss_winner_team_id' => $teamA->id, 'toss_decision' => 'bat']);

        $innings = Innings::create(array_merge([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ], $inningsAttributes));

        $batter = Player::factory()->create(['phone' => '9998887771', 'email' => 'batter@example.com']);
        $batterRegistration = PlayerRegistration::factory()->create([
            'edition_id' => $edition->id,
            'player_id' => $batter->id,
            'registration_fee' => 1234.56,
            'payment_status' => 'paid',
        ]);
        $striker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $teamA->id, 'player_registration_id' => $batterRegistration->id])->id,
        ]);
        $nonStriker = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $teamA->id])->id,
        ]);

        $bowlerPlayer = Player::factory()->create(['phone' => '9998887772', 'email' => 'bowler@example.com']);
        $bowlerRegistration = PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'player_id' => $bowlerPlayer->id]);
        $bowler = MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => TeamPlayer::factory()->create(['edition_team_id' => $teamB->id, 'player_registration_id' => $bowlerRegistration->id])->id,
        ]);

        return [$match->fresh(), $innings->fresh(), $striker, $nonStriker, $bowler, $batter, $bowlerPlayer];
    }

    /**
     * Records a delivery. When the caller submits the same two batters
     * used for the innings so far (just possibly at the wrong end), the
     * pair is reordered to match Phase 3.33's expected batting ends —
     * these fixture batters never get dismissed, so their identities
     * are fixed; only which end they're occupying can legitimately
     * differ once an over boundary has swapped them.
     */
    private function ball(GameMatch $match, Innings $innings, array $overrides): void
    {
        $innings = $innings->fresh();
        $state = app(DeliveryService::class)->expectedBattingState($innings);

        if (! $state['first_ball'] && ! $state['requires_replacement']) {
            $pair = [$overrides['striker_match_player_id'], $overrides['non_striker_match_player_id']];
            if (in_array($state['striker_id'], $pair, true) && in_array($state['non_striker_id'], $pair, true)) {
                $overrides['striker_match_player_id'] = $state['striker_id'];
                $overrides['non_striker_match_player_id'] = $state['non_striker_id'];
            }
        }

        app(DeliveryService::class)->recordDelivery($match, $innings, $overrides);
    }

    // ----- Public access / availability -----

    public function test_guest_can_access_live_page_and_live_data_endpoint_when_innings_exists(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        $this->get(route('public.matches.live', $match))->assertOk()->assertSee('Ball-by-Ball');

        $response = $this->getJson(route('public.matches.live-data', $match));
        $response->assertOk();
        $response->assertJsonStructure(['match_status', 'match_result', 'should_poll', 'innings', 'recent_deliveries']);
    }

    public function test_match_without_innings_shows_clean_unavailable_state(): void
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $match = GameMatch::factory()->create([
            'edition_id' => $edition->id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'scheduled',
        ]);

        $this->get(route('public.matches.live', $match))
            ->assertRedirect(route('public.matches.show', $match));

        $this->followRedirects($this->get(route('public.matches.live', $match)))
            ->assertSee('Ball-by-ball coverage will be available once scoring begins.');
    }

    public function test_completed_match_with_history_remains_viewable_and_stops_polling(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        // Delivery recorded while live; the match/innings are finalized
        // afterward, mirroring how a real match actually completes.
        $match->update(['match_status' => 'completed', 'match_result' => 'Team A won by 10 runs']);
        $innings->update(['status' => 'completed']);
        $match = $match->fresh();

        $response = $this->get(route('public.matches.live', $match));
        $response->assertOk();
        $response->assertSee('Team A won by 10 runs');

        $json = $this->getJson(route('public.matches.live-data', $match))->json();
        $this->assertFalse($json['should_poll']);
        $this->assertSame('Team A won by 10 runs', $json['match_result']);
        $this->assertNotEmpty($json['recent_deliveries']);
    }

    public function test_scheduled_match_with_existing_innings_is_viewable_but_not_polled(): void
    {
        // The real Phase 3.18 dev-data finding: innings existing while
        // match_status is stale/malformed as 'scheduled'. Must remain
        // viewable, but must never be marked for polling.
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 1,
        ]);

        $match->update(['match_status' => 'scheduled']);
        $match = $match->fresh();

        $response = $this->get(route('public.matches.live', $match));
        $response->assertOk();
        $response->assertSee('data-should-poll="0"', false);

        $json = $this->getJson(route('public.matches.live-data', $match))->json();
        $this->assertFalse($json['should_poll']);
    }

    // ----- Current score -----

    public function test_live_page_uses_cached_innings_score_and_overs_notation(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        for ($i = 0; $i < 7; $i++) {
            $this->ball($match, $innings, [
                'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
                'runs_off_bat' => 0,
            ]);
        }

        $response = $this->get(route('public.matches.live', $match));
        $response->assertOk();
        $response->assertSee('0/0');
        $response->assertSee('1.1 overs'); // 7 legal balls -> 1.1, never decimal "1.17"
    }

    // ----- Deliveries -----

    public function test_recent_deliveries_ordered_by_sequence_bounded_and_illegal_labels_preserved(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $base = ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id];

        // A wide followed by a legal ball — both should carry the same
        // over.ball label (0.1), per Phase 3.13's established rule, but
        // remain distinctly and correctly ordered by delivery_sequence.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 1]));

        $json = $this->getJson(route('public.matches.live-data', $match))->json();
        $deliveries = $json['recent_deliveries'];

        // delivery_sequence DESC: most recent (the legal ball) first.
        $this->assertCount(2, $deliveries);
        $this->assertSame('1', $deliveries[0]['outcome_label']);
        $this->assertSame('Wd', $deliveries[1]['outcome_label']);
        $this->assertSame('0.1', $deliveries[0]['ball_label']);
        $this->assertSame('0.1', $deliveries[1]['ball_label']); // legitimately repeated, not "fixed"
    }

    public function test_recent_deliveries_window_is_bounded(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $base = ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id];

        foreach (range(1, 35) as $i) {
            $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0]));
        }

        $json = $this->getJson(route('public.matches.live-data', $match))->json();

        $this->assertLessThanOrEqual(30, count($json['recent_deliveries']));
    }

    public function test_stored_commentary_shown_and_null_commentary_gets_fallback(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler, $batter, $bowlerPlayer] = $this->matchWithInningsAndPlayers();
        $base = ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id];

        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 4, 'commentary' => 'Cracking cover drive!']));

        $json = $this->getJson(route('public.matches.live-data', $match))->json();
        $delivery = $json['recent_deliveries'][0];

        $this->assertSame('Cracking cover drive!', $delivery['commentary']);
        $this->assertSame($batter->name, $delivery['striker']);
        $this->assertSame($bowlerPlayer->name, $delivery['bowler']);

        // No commentary provided -> a deterministic generated fallback.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0]));
        $json = $this->getJson(route('public.matches.live-data', $match))->json();
        $fallback = $json['recent_deliveries'][0]['commentary'];

        $this->assertStringContainsString($bowlerPlayer->name.' to '.$batter->name, $fallback);
        $this->assertStringContainsString('no run', $fallback);
    }

    public function test_wicket_delivery_renders_clearly(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $striker->id,
        ]);

        $json = $this->getJson(route('public.matches.live-data', $match))->json();
        $delivery = $json['recent_deliveries'][0];

        $this->assertSame('W', $delivery['outcome_label']);
        $this->assertTrue($delivery['is_wicket']);
        $this->assertStringContainsString('OUT — bowled', $delivery['commentary']);
    }

    // ----- Privacy -----

    public function test_live_page_and_endpoint_never_expose_private_data(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler, $batter, $bowlerPlayer] = $this->matchWithInningsAndPlayers();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4,
        ]);

        $page = $this->get(route('public.matches.live', $match));
        $page->assertOk();
        $page->assertDontSee($batter->phone);
        $page->assertDontSee($batter->email);
        $page->assertDontSee($bowlerPlayer->phone);
        $page->assertDontSee($bowlerPlayer->email);
        $page->assertDontSee('1234.56');

        $jsonResponse = $this->getJson(route('public.matches.live-data', $match));
        $raw = $jsonResponse->getContent();
        $this->assertStringNotContainsString($batter->phone, $raw);
        $this->assertStringNotContainsString($batter->email, $raw);
        $this->assertStringNotContainsString($bowlerPlayer->phone, $raw);
        $this->assertStringNotContainsString($bowlerPlayer->email, $raw);
        $this->assertStringNotContainsString('1234.56', $raw);
        $this->assertStringNotContainsString('payment_status', $raw);
        $this->assertStringNotContainsString('registration_fee', $raw);
    }

    // ----- Security -----

    public function test_no_public_mutation_routes_exist_and_admin_routes_remain_protected(): void
    {
        [$match] = $this->matchWithInningsAndPlayers();

        $this->post(route('public.matches.live-data', $match))->assertStatus(405);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.editions.index'))->assertRedirect(route('admin.login'));
    }

    // ----- Query behaviour -----

    public function test_live_data_endpoint_query_count_stays_bounded_as_deliveries_grow(): void
    {
        [$match, $innings, $striker, $nonStriker, $bowler] = $this->matchWithInningsAndPlayers();
        $base = ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id];

        // Runs off bat stay even (Phase 3.33: an odd run rotates strike),
        // so the same fixed striker/non-striker pair remains valid for
        // all 30 balls — this test only cares about query counts, not
        // the specific runs scored.
        foreach (range(1, 30) as $i) {
            $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0]));
        }

        DB::enableQueryLog();
        $this->getJson(route('public.matches.live-data', $match))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 30 recent deliveries must not cause 30x relationship queries —
        // a generous ceiling, not a tight budget.
        $this->assertLessThan(20, $queryCount);
    }
}
