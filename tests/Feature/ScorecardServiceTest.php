<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use App\Services\Scoring\DeliveryService;
use App\Services\Scoring\ScorecardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Deliberately lives outside tests/Feature/Admin — ScorecardService has
 * no admin/HTTP dependency (see its docblock), and these tests exercise
 * it directly, the same way a future public-website consumer would.
 */
class ScorecardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $deliveries;

    private ScorecardService $scorecards;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveries = app(DeliveryService::class);
        $this->scorecards = app(ScorecardService::class);
    }

    /**
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

    private function ball(GameMatch $match, Innings $innings, array $overrides): void
    {
        $this->deliveries->recordDelivery($match, $innings->fresh(), $overrides);
    }

    private function battingRowFor(array $card, int $matchPlayerId): ?array
    {
        return collect($card['battingRows'])->first(fn ($row) => $row['matchPlayer']->id === $matchPlayerId);
    }

    private function bowlingRowFor(array $card, int $matchPlayerId): ?array
    {
        return collect($card['bowlingRows'])->first(fn ($row) => $row['matchPlayer']->id === $matchPlayerId);
    }

    // ----- Batting statistics -----

    public function test_batting_figures_for_dot_single_four_six(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $base = [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ];

        // The single (an odd run) is scored last — an odd run always
        // rotates strike (Phase 3.33), so batter[0] could not otherwise
        // legitimately face every one of these four balls in a row.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 4]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 6]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 1]));

        $card = $this->scorecards->getInningsScorecard($innings->fresh());
        $row = $this->battingRowFor($card, $battingPlayers[0]->id);

        $this->assertSame(11, $row['runs']);
        $this->assertSame(4, $row['balls']);
        $this->assertSame(1, $row['fours']);
        $this->assertSame(1, $row['sixes']);
        $this->assertEqualsWithDelta(275.0, $row['strikeRate'], 0.01);
    }

    public function test_wide_does_not_count_as_ball_faced(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame(0, $row['balls']);
        $this->assertSame(0, $row['runs']);
        $this->assertSame(0.0, $row['strikeRate']);
    }

    public function test_no_ball_does_not_count_as_ball_faced_but_bat_runs_still_credited(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 2, 'extra_type' => 'no_ball', 'extra_amount' => 1,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame(0, $row['balls']);
        $this->assertSame(2, $row['runs']);
    }

    public function test_bye_counts_as_ball_faced_with_no_runs_credited(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'extra_type' => 'bye', 'extra_amount' => 2,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame(1, $row['balls']);
        $this->assertSame(0, $row['runs']);
    }

    public function test_leg_bye_counts_as_ball_faced_with_no_runs_credited(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'extra_type' => 'leg_bye', 'extra_amount' => 1,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame(1, $row['balls']);
        $this->assertSame(0, $row['runs']);
    }

    // ----- Bowling statistics -----

    public function test_bowling_figures_and_economy(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $base = [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ];

        // The odd run is scored last — see the strike-rotation note above.
        // Bowling figures are unaffected by which end faces which ball.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 4]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 1]));

        $row = $this->bowlingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $bowlingPlayers[0]->id);

        $this->assertSame(3, $row['legalBalls']);
        $this->assertSame('0.3', $row['oversDisplay']);
        $this->assertSame(5, $row['runsConceded']);
        $this->assertEqualsWithDelta(10.0, $row['economy'], 0.01);
    }

    public function test_byes_and_leg_byes_not_charged_to_bowler(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $base = [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0,
        ];

        // 3 byes is an odd running-run count and would rotate strike —
        // scored last so bowling figures (unaffected by end) stay simple.
        $this->ball($match, $innings, array_merge($base, ['extra_type' => 'leg_bye', 'extra_amount' => 2]));
        $this->ball($match, $innings, array_merge($base, ['extra_type' => 'bye', 'extra_amount' => 3]));

        $row = $this->bowlingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $bowlingPlayers[0]->id);

        $this->assertSame(0, $row['runsConceded']);
        $this->assertSame(2, $row['legalBalls']);
        $this->assertSame(0.0, $row['economy']);
    }

    public function test_wide_and_no_ball_charged_to_bowler(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $base = [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ];

        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 4, 'extra_type' => 'no_ball', 'extra_amount' => 1]));

        $row = $this->bowlingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $bowlingPlayers[0]->id);

        $this->assertSame(6, $row['runsConceded']); // 1 wide + (1 no-ball penalty + 4 off the bat)
        $this->assertSame(1, $row['wideRuns']);
        $this->assertSame(1, $row['noBallRuns']);
        $this->assertSame(0, $row['legalBalls']);
    }

    public function test_run_out_increments_team_wicket_not_bowler_wicket(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'run_out',
            'dismissed_match_player_id' => $battingPlayers[1]->id,
        ]);

        $row = $this->bowlingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $bowlingPlayers[0]->id);

        $this->assertSame(1, $innings->fresh()->total_wickets);
        $this->assertSame(0, $row['wickets']);
    }

    public function test_caught_increments_both_team_and_bowler_wicket(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'caught',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]);

        $row = $this->bowlingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $bowlingPlayers[0]->id);

        $this->assertSame(1, $innings->fresh()->total_wickets);
        $this->assertSame(1, $row['wickets']);
    }

    // ----- Dismissal formatting -----

    public function test_dismissal_text_bowled(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);
        $bowlerName = $bowlingPlayers[0]->teamPlayer->playerRegistration->player->name;

        $this->assertSame("b {$bowlerName}", $row['dismissalText']);
    }

    public function test_dismissal_text_caught(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'caught',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);
        $bowlerName = $bowlingPlayers[0]->teamPlayer->playerRegistration->player->name;
        $fielderName = $bowlingPlayers[1]->teamPlayer->playerRegistration->player->name;

        $this->assertSame("c {$fielderName} b {$bowlerName}", $row['dismissalText']);
    }

    public function test_dismissal_text_lbw(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'lbw',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);
        $bowlerName = $bowlingPlayers[0]->teamPlayer->playerRegistration->player->name;

        $this->assertSame("lbw b {$bowlerName}", $row['dismissalText']);
    }

    public function test_dismissal_text_stumped(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'stumped',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);
        $bowlerName = $bowlingPlayers[0]->teamPlayer->playerRegistration->player->name;
        $fielderName = $bowlingPlayers[1]->teamPlayer->playerRegistration->player->name;

        $this->assertSame("st {$fielderName} b {$bowlerName}", $row['dismissalText']);
    }

    public function test_dismissal_text_run_out_with_fielder(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'run_out',
            'dismissed_match_player_id' => $battingPlayers[1]->id,
            'fielder_match_player_id' => $bowlingPlayers[1]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[1]->id);
        $fielderName = $bowlingPlayers[1]->teamPlayer->playerRegistration->player->name;

        $this->assertSame("run out ({$fielderName})", $row['dismissalText']);
    }

    public function test_dismissal_text_run_out_without_fielder(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'run_out',
            'dismissed_match_player_id' => $battingPlayers[1]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[1]->id);

        $this->assertSame('run out', $row['dismissalText']);
    }

    public function test_dismissal_text_hit_wicket(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'hit_wicket',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);
        $bowlerName = $bowlingPlayers[0]->teamPlayer->playerRegistration->player->name;

        $this->assertSame("hit wicket b {$bowlerName}", $row['dismissalText']);
    }

    public function test_dismissal_text_obstructing_field(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'obstructing_field',
            'dismissed_match_player_id' => $battingPlayers[0]->id,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame('obstructing the field', $row['dismissalText']);
    }

    public function test_not_out_for_undismissed_batter(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 1,
        ]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame('not out', $row['dismissalText']);
    }

    // ----- Extras -----

    public function test_extras_breakdown_agrees_with_innings_cache(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $base = [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ];

        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 2]));
        // The no-ball's 1 batter run is an odd running-run count and
        // rotates strike (Phase 3.33) — the 3 byes on the next ball are
        // therefore run by the other end.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 1, 'extra_type' => 'no_ball', 'extra_amount' => 1]));
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'extra_type' => 'bye', 'extra_amount' => 3,
        ]);
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'leg_bye', 'extra_amount' => 4]));

        $card = $this->scorecards->getInningsScorecard($innings->fresh());

        $this->assertSame(2, $card['extras']['wides']);
        $this->assertSame(1, $card['extras']['noBalls']);
        $this->assertSame(3, $card['extras']['byes']);
        $this->assertSame(4, $card['extras']['legByes']);
        $this->assertSame(0, $card['extras']['penalty']);
        $this->assertSame(10, $card['extras']['total']);
        $this->assertSame($innings->fresh()->extras, $card['extras']['total']);
        $this->assertSame($innings->fresh()->total_runs, $card['innings']->total_runs);
    }

    // ----- Fall of wickets -----

    public function test_fall_of_wickets_tracks_cumulative_score_and_over_notation(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $base = [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
        ];

        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 4])); // 4
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 2])); // 6
        $this->ball($match, $innings, array_merge($base, [
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $battingPlayers[0]->id,
        ])); // wicket 1 at 6, ball 0.3
        $this->ball($match, $innings, array_merge($base, [
            'striker_match_player_id' => $battingPlayers[2]->id, 'runs_off_bat' => 3,
        ])); // 9 — an odd (3) run off the new batter rotates strike
        $this->ball($match, $innings, [
            // Strike rotated after the previous ball's 3 runs: batter[1]
            // (who was at the non-striker end) is now on strike.
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[2]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0,
            'is_wicket' => 1, 'wicket_type' => 'run_out', 'dismissed_match_player_id' => $battingPlayers[1]->id,
        ]); // wicket 2 at 9, ball 0.5

        $fow = $this->scorecards->getInningsScorecard($innings->fresh())['fallOfWickets'];

        $this->assertCount(2, $fow);
        $this->assertSame(1, $fow[0]['wicketNumber']);
        $this->assertSame(6, $fow[0]['score']);
        $this->assertSame('0.3', $fow[0]['overNotation']);
        $this->assertSame($battingPlayers[0]->teamPlayer->playerRegistration->player->name, $fow[0]['player']);

        $this->assertSame(2, $fow[1]['wicketNumber']);
        $this->assertSame(9, $fow[1]['score']);
        $this->assertSame('0.5', $fow[1]['overNotation']);
        $this->assertSame($battingPlayers[1]->teamPlayer->playerRegistration->player->name, $fow[1]['player']);
    }

    // ----- Ordering -----

    public function test_batting_order_follows_first_appearance_not_id_or_alphabetical(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        // Opener striker/non-striker are the HIGH-id and MID-id players;
        // the LOW-id player comes in later after a wicket — the exact
        // opposite of ascending-id order, to prove ordering isn't by id.
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[2]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0,
        ]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[2]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled',
            'dismissed_match_player_id' => $battingPlayers[2]->id,
        ]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 1,
        ]);

        $order = array_map(fn ($row) => $row['matchPlayer']->id, $this->scorecards->getInningsScorecard($innings->fresh())['battingRows']);

        $this->assertSame([$battingPlayers[2]->id, $battingPlayers[1]->id, $battingPlayers[0]->id], $order);
    }

    public function test_bowling_order_follows_first_appearance(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();

        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[2]->id,
            'runs_off_bat' => 0,
        ]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 0,
        ]);

        $order = array_map(fn ($row) => $row['matchPlayer']->id, $this->scorecards->getInningsScorecard($innings->fresh())['bowlingRows']);

        $this->assertSame([$bowlingPlayers[2]->id, $bowlingPlayers[0]->id], $order);
    }

    // ----- Did not bat -----

    public function test_did_not_bat_excludes_players_who_never_appeared(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 1,
        ]);

        $card = $this->scorecards->getInningsScorecard($innings->fresh());
        $didNotBatIds = $card['didNotBat']->pluck('id')->all();

        $this->assertSame([$battingPlayers[2]->id], $didNotBatIds);
    }

    public function test_non_striker_only_appearance_excludes_from_did_not_bat(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        // battingPlayers[1] is only ever the non-striker.
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 1,
        ]);

        $card = $this->scorecards->getInningsScorecard($innings->fresh());

        $this->assertFalse($card['didNotBat']->contains('id', $battingPlayers[1]->id));
        $this->assertTrue(collect($card['battingRows'])->contains(fn ($row) => $row['matchPlayer']->id === $battingPlayers[1]->id));
    }

    // ----- Historical / active status -----

    public function test_scorecard_remains_correct_after_player_deactivated(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 4,
        ]);

        $battingPlayers[0]->teamPlayer->playerRegistration->player->update(['is_active' => false]);

        $row = $this->battingRowFor($this->scorecards->getInningsScorecard($innings->fresh()), $battingPlayers[0]->id);

        $this->assertSame(4, $row['runs']);
        $this->assertSame($battingPlayers[0]->teamPlayer->playerRegistration->player->name, $row['matchPlayer']->teamPlayer->playerRegistration->player->name);
    }

    // ----- Multiple / empty innings -----

    public function test_match_scorecard_covers_independent_innings(): void
    {
        [$match, $innings1, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings1, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 4,
        ]);
        $innings1->fresh()->update(['status' => 'completed']);

        $innings2 = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 2,
            'batting_team_id' => $match->edition_team_b_id,
            'bowling_team_id' => $match->edition_team_a_id,
            'status' => 'live',
        ]);
        $this->ball($match, $innings2, [
            'striker_match_player_id' => $bowlingPlayers[0]->id, // now batting
            'non_striker_match_player_id' => $bowlingPlayers[1]->id,
            'bowler_match_player_id' => $battingPlayers[0]->id, // now bowling
            'runs_off_bat' => 1,
        ]);

        $cards = $this->scorecards->getMatchScorecard($match->fresh());

        $this->assertCount(2, $cards);
        $this->assertSame(1, $cards[0]['innings']->innings_number);
        $this->assertSame(4, $this->battingRowFor($cards[0], $battingPlayers[0]->id)['runs']);
        $this->assertSame(2, $cards[1]['innings']->innings_number);
        $this->assertSame(1, $this->battingRowFor($cards[1], $bowlingPlayers[0]->id)['runs']);
    }

    public function test_empty_innings_renders_without_errors(): void
    {
        [, $innings] = $this->matchWithLiveInnings();

        $card = $this->scorecards->getInningsScorecard($innings->fresh());

        $this->assertSame([], $card['battingRows']);
        $this->assertSame([], $card['bowlingRows']);
        $this->assertSame([], $card['fallOfWickets']);
        $this->assertNull($card['lastDelivery']);
        $this->assertSame(0, $card['extras']['total']);
        $this->assertCount(3, $card['didNotBat']);
    }

    // ----- Last delivery context -----

    public function test_last_delivery_context_reflects_most_recent_delivery(): void
    {
        [$match, $innings, $battingPlayers, $bowlingPlayers] = $this->matchWithLiveInnings();
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[0]->id,
            'non_striker_match_player_id' => $battingPlayers[1]->id,
            'bowler_match_player_id' => $bowlingPlayers[0]->id,
            'runs_off_bat' => 1,
        ]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $battingPlayers[1]->id,
            'non_striker_match_player_id' => $battingPlayers[0]->id,
            'bowler_match_player_id' => $bowlingPlayers[1]->id,
            'runs_off_bat' => 2,
        ]);

        $last = $this->scorecards->getInningsScorecard($innings->fresh())['lastDelivery'];

        $this->assertSame($battingPlayers[1]->teamPlayer->playerRegistration->player->name, $last['striker']);
        $this->assertSame($battingPlayers[0]->teamPlayer->playerRegistration->player->name, $last['nonStriker']);
        $this->assertSame($bowlingPlayers[1]->teamPlayer->playerRegistration->player->name, $last['bowler']);
    }
}
