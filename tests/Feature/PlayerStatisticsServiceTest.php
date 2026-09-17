<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Scoring\DeliveryService;
use App\Services\Statistics\PlayerStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deliberately lives outside tests/Feature/Admin — PlayerStatisticsService
 * has no admin/HTTP dependency (see its docblock), and these tests
 * exercise it directly, the same way a future public-website consumer
 * would.
 */
class PlayerStatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $deliveries;

    private PlayerStatisticsService $statistics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveries = app(DeliveryService::class);
        $this->statistics = app(PlayerStatisticsService::class);
    }

    /**
     * @return array{0: EditionTeam, 1: EditionTeam}
     */
    private function editionTeams(Edition $edition): array
    {
        return [
            EditionTeam::factory()->create(['edition_id' => $edition->id]),
            EditionTeam::factory()->create(['edition_id' => $edition->id]),
        ];
    }

    /**
     * A live match between two specific EditionTeams (teamA batting
     * first), with a live Innings #1 ready to be scored. Takes real
     * EditionTeam rows (not a bare Edition) so the same team/player can
     * be reused across several matches within one edition.
     *
     * @return array{0: GameMatch, 1: Innings}
     */
    private function matchBetween(EditionTeam $teamA, EditionTeam $teamB, array $matchAttributes = []): array
    {
        $match = GameMatch::factory()->create(array_merge([
            'edition_id' => $teamA->edition_id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
            'match_status' => 'live',
            'started_at' => now(),
        ], $matchAttributes));

        $match->update(['toss_winner_team_id' => $teamA->id, 'toss_decision' => 'bat']);

        $innings = Innings::create([
            'match_id' => $match->id,
            'innings_number' => 1,
            'batting_team_id' => $teamA->id,
            'bowling_team_id' => $teamB->id,
            'status' => 'live',
        ]);

        return [$match->fresh(), $innings->fresh()];
    }

    /**
     * A brand-new edition with two fresh teams and a live match between
     * them — for tests that only need a single, self-contained match.
     *
     * @return array{0: GameMatch, 1: Innings}
     */
    private function newLiveMatch(): array
    {
        [$teamA, $teamB] = $this->editionTeams(Edition::factory()->create());

        return $this->matchBetween($teamA, $teamB);
    }

    /**
     * Registers $player (or a new one) into this EditionTeam's squad —
     * the Player -> PlayerRegistration -> TeamPlayer chain. A player has
     * exactly one registration per edition, so call this ONCE per
     * player per edition and reuse the returned TeamPlayer across every
     * match that team plays (via selectForMatch()) — never call it
     * again for the same player within the same edition.
     */
    private function squadPlayer(EditionTeam $editionTeam, ?Player $player = null): TeamPlayer
    {
        $player ??= Player::factory()->create();

        $registration = PlayerRegistration::factory()->create([
            'edition_id' => $editionTeam->edition_id,
            'player_id' => $player->id,
        ]);

        return TeamPlayer::factory()->create([
            'edition_team_id' => $editionTeam->id,
            'player_registration_id' => $registration->id,
        ]);
    }

    private function selectForMatch(GameMatch $match, TeamPlayer $teamPlayer): MatchPlayer
    {
        return MatchPlayer::factory()->create([
            'match_id' => $match->id,
            'team_player_id' => $teamPlayer->id,
        ]);
    }

    private function ball(GameMatch $match, Innings $innings, array $overrides): void
    {
        $this->deliveries->recordDelivery($match, $innings->fresh(), $overrides);
    }

    // ----- Batting -----

    public function test_runs_balls_and_boundaries_aggregate_across_multiple_deliveries(): void
    {
        [$match, $innings] = $this->newLiveMatch();
        $teamA = $match->teamA;
        $teamB = $match->teamB;
        $striker = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $nonStriker = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $bowler = $this->selectForMatch($match, $this->squadPlayer($teamB));
        $base = ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id];

        // The single (an odd run) is scored last — an odd run always
        // rotates strike (Phase 3.33), so $striker could not otherwise
        // legitimately face every one of these deliveries in a row. The
        // leg-bye amount is even (2, not 1) for the same reason.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 4]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 6]));
        // A wide and a no-ball must not count as balls faced.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'no_ball', 'extra_amount' => 1]));
        // A bye and a leg-bye must count as balls faced, with no runs credited.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'bye', 'extra_amount' => 2]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'leg_bye', 'extra_amount' => 2]));
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 1]));

        $stats = $this->statistics->getPlayerStatistics($striker->teamPlayer->playerRegistration->player);

        $this->assertSame(11, $stats['batting']['runs']);
        $this->assertSame(5, $stats['batting']['balls_faced']); // 3 normal + bye + leg-bye
        $this->assertSame(1, $stats['batting']['fours']);
        $this->assertSame(1, $stats['batting']['sixes']);
    }

    public function test_innings_batted_counts_non_striker_only_appearance(): void
    {
        [$match, $innings] = $this->newLiveMatch();
        $teamA = $match->teamA;
        $teamB = $match->teamB;
        $player = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $otherBatter = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $bowler = $this->selectForMatch($match, $this->squadPlayer($teamB));

        // $player is only ever the non-striker — never faces a ball.
        $this->ball($match, $innings, [
            'striker_match_player_id' => $otherBatter->id,
            'non_striker_match_player_id' => $player->id,
            'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 1,
        ]);

        $stats = $this->statistics->getPlayerStatistics($player->teamPlayer->playerRegistration->player);

        $this->assertSame(1, $stats['batting']['innings_batted']);
        $this->assertSame(0, $stats['batting']['runs']);
        $this->assertSame(0, $stats['batting']['balls_faced']);
    }

    public function test_batting_average_uses_dismissals_not_innings_and_is_null_when_never_dismissed(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        [$teamA, $teamB] = $this->editionTeams($edition);
        $teamPlayer = $this->squadPlayer($teamA, $player);

        [$matchA, $inningsA] = $this->matchBetween($teamA, $teamB);
        $striker = $this->selectForMatch($matchA, $teamPlayer);
        $nonStriker = $this->selectForMatch($matchA, $this->squadPlayer($teamA));
        $bowler = $this->selectForMatch($matchA, $this->squadPlayer($teamB));

        $this->ball($matchA, $inningsA, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $striker->id,
        ]);

        // Never dismissed across a second match: average must stay null, not divide by zero.
        [$matchB, $inningsB] = $this->matchBetween($teamA, $teamB);
        $strikerB = $this->selectForMatch($matchB, $teamPlayer);
        $nonStrikerB = $this->selectForMatch($matchB, $this->squadPlayer($teamA));
        $bowlerB = $this->selectForMatch($matchB, $this->squadPlayer($teamB));
        $this->ball($matchB, $inningsB, [
            'striker_match_player_id' => $strikerB->id, 'non_striker_match_player_id' => $nonStrikerB->id, 'bowler_match_player_id' => $bowlerB->id,
            'runs_off_bat' => 20,
        ]);

        $stats = $this->statistics->getPlayerStatistics($player);

        $this->assertSame(2, $stats['batting']['innings_batted']);
        $this->assertSame(1, $stats['batting']['not_outs']);
        $this->assertSame(20, $stats['batting']['runs']); // 0 + 20
        $this->assertSame(20.0, $stats['batting']['batting_average']); // 20 runs / 1 dismissal, not / 2 innings
    }

    public function test_zero_dismissals_does_not_divide_by_zero(): void
    {
        [$match, $innings] = $this->newLiveMatch();
        $player = Player::factory()->create();
        $striker = $this->selectForMatch($match, $this->squadPlayer($match->teamA, $player));
        $nonStriker = $this->selectForMatch($match, $this->squadPlayer($match->teamA));
        $bowler = $this->selectForMatch($match, $this->squadPlayer($match->teamB));

        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 5,
        ]);

        $stats = $this->statistics->getPlayerStatistics($player);

        $this->assertNull($stats['batting']['batting_average']);
    }

    public function test_highest_score_is_the_maximum_across_innings(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        [$teamA, $teamB] = $this->editionTeams($edition);
        $teamPlayer = $this->squadPlayer($teamA, $player);

        [$matchA, $inningsA] = $this->matchBetween($teamA, $teamB);
        $strikerA = $this->selectForMatch($matchA, $teamPlayer);
        $nonStrikerA = $this->selectForMatch($matchA, $this->squadPlayer($teamA));
        $bowlerA = $this->selectForMatch($matchA, $this->squadPlayer($teamB));
        $this->ball($matchA, $inningsA, ['striker_match_player_id' => $strikerA->id, 'non_striker_match_player_id' => $nonStrikerA->id, 'bowler_match_player_id' => $bowlerA->id, 'runs_off_bat' => 20]);

        [$matchB, $inningsB] = $this->matchBetween($teamA, $teamB);
        $strikerB = $this->selectForMatch($matchB, $teamPlayer);
        $nonStrikerB = $this->selectForMatch($matchB, $this->squadPlayer($teamA));
        $bowlerB = $this->selectForMatch($matchB, $this->squadPlayer($teamB));
        $this->ball($matchB, $inningsB, ['striker_match_player_id' => $strikerB->id, 'non_striker_match_player_id' => $nonStrikerB->id, 'bowler_match_player_id' => $bowlerB->id, 'runs_off_bat' => 35]);
        // 35 is an odd run — strike rotates (Phase 3.33), so $strikerB
        // has crossed to the non-striker end by the next ball.
        $this->ball($matchB, $inningsB, [
            'striker_match_player_id' => $nonStrikerB->id, 'non_striker_match_player_id' => $strikerB->id, 'bowler_match_player_id' => $bowlerB->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $strikerB->id,
        ]);

        $stats = $this->statistics->getPlayerStatistics($player);

        $this->assertSame(35, $stats['batting']['highest_score']);
        $this->assertFalse($stats['batting']['highest_score_not_out']);
        $this->assertSame(55, $stats['batting']['runs']);
        // 1 ball in innings A + 1 in innings B: the wicket ball dismisses
        // $strikerB after they've crossed to the non-striker end (the
        // preceding 35 was an odd run), so that ball is not a ball
        // faced by them — only the 35-run ball itself is.
        $this->assertSame(2, $stats['batting']['balls_faced']);
        $this->assertEqualsWithDelta(2750.0, $stats['batting']['strike_rate'], 0.01); // 55/2*100
    }

    public function test_highest_score_tie_break_prefers_the_not_out_innings(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        [$teamA, $teamB] = $this->editionTeams($edition);
        $teamPlayer = $this->squadPlayer($teamA, $player);

        // Match A: dismissed for 25.
        [$matchA, $inningsA] = $this->matchBetween($teamA, $teamB);
        $strikerA = $this->selectForMatch($matchA, $teamPlayer);
        $nonStrikerA = $this->selectForMatch($matchA, $this->squadPlayer($teamA));
        $bowlerA = $this->selectForMatch($matchA, $this->squadPlayer($teamB));
        $this->ball($matchA, $inningsA, ['striker_match_player_id' => $strikerA->id, 'non_striker_match_player_id' => $nonStrikerA->id, 'bowler_match_player_id' => $bowlerA->id, 'runs_off_bat' => 25]);
        // 25 is an odd run — strike rotates (Phase 3.33), so $strikerA
        // has crossed to the non-striker end by the next ball.
        $this->ball($matchA, $inningsA, [
            'striker_match_player_id' => $nonStrikerA->id, 'non_striker_match_player_id' => $strikerA->id, 'bowler_match_player_id' => $bowlerA->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $strikerA->id,
        ]);

        // Match B: exactly tied on runs (25), but not out.
        [$matchB, $inningsB] = $this->matchBetween($teamA, $teamB);
        $strikerB = $this->selectForMatch($matchB, $teamPlayer);
        $nonStrikerB = $this->selectForMatch($matchB, $this->squadPlayer($teamA));
        $bowlerB = $this->selectForMatch($matchB, $this->squadPlayer($teamB));
        $this->ball($matchB, $inningsB, ['striker_match_player_id' => $strikerB->id, 'non_striker_match_player_id' => $nonStrikerB->id, 'bowler_match_player_id' => $bowlerB->id, 'runs_off_bat' => 25]);

        $stats = $this->statistics->getPlayerStatistics($player);

        $this->assertSame(25, $stats['batting']['highest_score']);
        $this->assertTrue($stats['batting']['highest_score_not_out']);
    }

    // ----- Bowling -----

    public function test_bowling_figures_exclude_byes_leg_byes_and_non_credited_wickets(): void
    {
        [$match, $innings] = $this->newLiveMatch();
        $player = Player::factory()->create();
        $bowler = $this->selectForMatch($match, $this->squadPlayer($match->teamB, $player));
        $striker = $this->selectForMatch($match, $this->squadPlayer($match->teamA));
        $nonStriker = $this->selectForMatch($match, $this->squadPlayer($match->teamA));
        $base = ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id];
        $newBatter = $this->selectForMatch($match, $this->squadPlayer($match->teamA));

        // Strike rotation (Phase 3.33): ball1's single run swaps ends,
        // so the wide/no-ball are faced from the other end; the 3 byes
        // swap back, putting $striker back on strike for the leg-byes.
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 1]));
        $this->ball($match, $innings, [
            'striker_match_player_id' => $nonStriker->id, 'non_striker_match_player_id' => $striker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'extra_type' => 'wide', 'extra_amount' => 1,
        ]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $nonStriker->id, 'non_striker_match_player_id' => $striker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 4, 'extra_type' => 'no_ball', 'extra_amount' => 1,
        ]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $nonStriker->id, 'non_striker_match_player_id' => $striker->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'extra_type' => 'bye', 'extra_amount' => 3,
        ]);
        $this->ball($match, $innings, array_merge($base, ['runs_off_bat' => 0, 'extra_type' => 'leg_bye', 'extra_amount' => 2]));
        // A run-out must never be credited to the bowler.
        $this->ball($match, $innings, array_merge($base, [
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'run_out', 'dismissed_match_player_id' => $nonStriker->id,
        ]));
        // $striker survives at the striker end; $newBatter replaces
        // $nonStriker. A caught dismissal must be credited to the bowler.
        $this->ball($match, $innings, [
            'striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $newBatter->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'caught', 'dismissed_match_player_id' => $striker->id, 'fielder_match_player_id' => $bowler->id,
        ]);

        $stats = $this->statistics->getPlayerStatistics($player);

        // Legal balls: normal(1) + bye(1) + leg-bye(1) + run-out ball(1) + caught ball(1) = 5; wide/no-ball excluded.
        $this->assertSame(5, $stats['bowling']['legal_balls']);
        $this->assertSame('0.5', $stats['bowling']['overs']);
        // Runs conceded: 1 (bat) + 1 (wide) + 5 (no-ball: 1 penalty + 4 bat) = 7. Byes(3)/leg-byes(2) excluded.
        $this->assertSame(7, $stats['bowling']['runs_conceded']);
        $this->assertSame(1, $stats['bowling']['wickets']); // only the caught dismissal
        $this->assertEqualsWithDelta(8.4, $stats['bowling']['economy'], 0.01); // 7*6/5
    }

    public function test_bowling_average_null_with_zero_wickets_and_best_bowling_prefers_wickets_then_runs(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        [$teamA, $teamB] = $this->editionTeams($edition);
        $bowlingTeamPlayer = $this->squadPlayer($teamB, $player);

        // Match A: 1 wicket for 20 runs.
        [$matchA, $inningsA] = $this->matchBetween($teamA, $teamB);
        $bowlerA = $this->selectForMatch($matchA, $bowlingTeamPlayer);
        $strikerA = $this->selectForMatch($matchA, $this->squadPlayer($teamA));
        $nonStrikerA = $this->selectForMatch($matchA, $this->squadPlayer($teamA));
        $this->ball($matchA, $inningsA, ['striker_match_player_id' => $strikerA->id, 'non_striker_match_player_id' => $nonStrikerA->id, 'bowler_match_player_id' => $bowlerA->id, 'runs_off_bat' => 20]);
        $this->ball($matchA, $inningsA, [
            'striker_match_player_id' => $strikerA->id, 'non_striker_match_player_id' => $nonStrikerA->id, 'bowler_match_player_id' => $bowlerA->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $strikerA->id,
        ]);

        // Match B: 2 wickets for 25 runs — more wickets wins best bowling despite more runs.
        [$matchB, $inningsB] = $this->matchBetween($teamA, $teamB);
        $bowlerB = $this->selectForMatch($matchB, $bowlingTeamPlayer);
        $strikerB = $this->selectForMatch($matchB, $this->squadPlayer($teamA));
        $nonStrikerB = $this->selectForMatch($matchB, $this->squadPlayer($teamA));
        $newBatterB = $this->selectForMatch($matchB, $this->squadPlayer($teamA));

        // 25 is odd — strike rotates after ball1, so $strikerB is now at
        // the non-striker end (bowled may still dismiss either end —
        // this project's existing wicket-type rules were never that
        // strict; see DeliveryService's docblock).
        $this->ball($matchB, $inningsB, ['striker_match_player_id' => $strikerB->id, 'non_striker_match_player_id' => $nonStrikerB->id, 'bowler_match_player_id' => $bowlerB->id, 'runs_off_bat' => 25]);
        $this->ball($matchB, $inningsB, [
            'striker_match_player_id' => $nonStrikerB->id, 'non_striker_match_player_id' => $strikerB->id, 'bowler_match_player_id' => $bowlerB->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $strikerB->id,
        ]);
        // $nonStrikerB survives at the striker end; $newBatterB replaces
        // $strikerB at the vacant non-striker end.
        $this->ball($matchB, $inningsB, [
            'striker_match_player_id' => $nonStrikerB->id, 'non_striker_match_player_id' => $newBatterB->id, 'bowler_match_player_id' => $bowlerB->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $nonStrikerB->id,
        ]);

        $stats = $this->statistics->getPlayerStatistics($player);

        $this->assertSame(3, $stats['bowling']['wickets']);
        $this->assertSame(45, $stats['bowling']['runs_conceded']);
        $this->assertEqualsWithDelta(15.0, $stats['bowling']['bowling_average'], 0.01);
        $this->assertSame('2/25', $stats['bowling']['best_bowling']);
    }

    // ----- Filtering / identity -----

    public function test_career_aggregates_multiple_editions_and_edition_filter_isolates_one(): void
    {
        $player = Player::factory()->create();

        $editionOne = Edition::factory()->create();
        [$teamOneA, $teamOneB] = $this->editionTeams($editionOne);
        [$matchOne, $inningsOne] = $this->matchBetween($teamOneA, $teamOneB);
        $strikerOne = $this->selectForMatch($matchOne, $this->squadPlayer($teamOneA, $player));
        $nonStrikerOne = $this->selectForMatch($matchOne, $this->squadPlayer($teamOneA));
        $bowlerOne = $this->selectForMatch($matchOne, $this->squadPlayer($teamOneB));
        $this->ball($matchOne, $inningsOne, ['striker_match_player_id' => $strikerOne->id, 'non_striker_match_player_id' => $nonStrikerOne->id, 'bowler_match_player_id' => $bowlerOne->id, 'runs_off_bat' => 10]);

        $editionTwo = Edition::factory()->create();
        [$teamTwoA, $teamTwoB] = $this->editionTeams($editionTwo);
        [$matchTwo, $inningsTwo] = $this->matchBetween($teamTwoA, $teamTwoB);
        $strikerTwo = $this->selectForMatch($matchTwo, $this->squadPlayer($teamTwoA, $player));
        $nonStrikerTwo = $this->selectForMatch($matchTwo, $this->squadPlayer($teamTwoA));
        $bowlerTwo = $this->selectForMatch($matchTwo, $this->squadPlayer($teamTwoB));
        $this->ball($matchTwo, $inningsTwo, ['striker_match_player_id' => $strikerTwo->id, 'non_striker_match_player_id' => $nonStrikerTwo->id, 'bowler_match_player_id' => $bowlerTwo->id, 'runs_off_bat' => 15]);

        $career = $this->statistics->getPlayerStatistics($player);
        $this->assertSame(25, $career['batting']['runs']);
        $this->assertSame(2, $career['matches_played']);

        $editionOneOnly = $this->statistics->getPlayerStatistics($player, $editionOne);
        $this->assertSame(10, $editionOneOnly['batting']['runs']);
        $this->assertSame(1, $editionOneOnly['matches_played']);

        $editionTwoOnly = $this->statistics->getPlayerStatistics($player, $editionTwo);
        $this->assertSame(15, $editionTwoOnly['batting']['runs']);
    }

    // ----- Matches played -----

    public function test_matches_played_requires_actual_delivery_participation(): void
    {
        [$match] = $this->newLiveMatch();
        $player = Player::factory()->create();
        // Selected into the Playing XI but never involved in a delivery.
        $this->selectForMatch($match, $this->squadPlayer($match->teamA, $player));

        $stats = $this->statistics->getPlayerStatistics($player);

        $this->assertSame(0, $stats['matches_played']);
        $this->assertSame(0, $stats['batting']['innings_batted']);
    }

    // ----- Match history -----

    public function test_match_history_shows_only_participated_matches_with_correct_summaries(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        [$teamA, $teamB] = $this->editionTeams($edition);
        $teamPlayer = $this->squadPlayer($teamA, $player);

        [$matchPlayed, $inningsPlayed] = $this->matchBetween($teamA, $teamB);
        $striker = $this->selectForMatch($matchPlayed, $teamPlayer);
        $nonStriker = $this->selectForMatch($matchPlayed, $this->squadPlayer($teamA));
        $bowler = $this->selectForMatch($matchPlayed, $this->squadPlayer($teamB));
        $this->ball($matchPlayed, $inningsPlayed, ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id, 'runs_off_bat' => 4]);
        $this->ball($matchPlayed, $inningsPlayed, ['striker_match_player_id' => $striker->id, 'non_striker_match_player_id' => $nonStriker->id, 'bowler_match_player_id' => $bowler->id, 'runs_off_bat' => 2]);

        [$matchNotPlayed] = $this->matchBetween($teamA, $teamB);
        $this->selectForMatch($matchNotPlayed, $teamPlayer); // selected, never scored

        $history = $this->statistics->getPlayerMatchHistory($player);

        $this->assertSame(1, $history->total());
        $row = $history->items()[0];
        $this->assertSame($matchPlayed->id, $row['match']->id);
        $this->assertSame(6, $row['batting']['runs']);
        $this->assertSame(2, $row['batting']['balls']);
        $this->assertTrue($row['batting']['not_out']);
        $this->assertNull($row['bowling']);
    }

    // ----- Edition leaderboard -----

    public function test_top_run_scorers_and_wicket_takers_are_sorted_and_edition_specific(): void
    {
        [$match, $innings] = $this->newLiveMatch();
        $teamA = $match->teamA;
        $teamB = $match->teamB;

        $lowScorer = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $highScorer = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $newBatter = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $bowler = $this->selectForMatch($match, $this->squadPlayer($teamB));

        // 5 is odd — strike rotates after ball1, so $highScorer faces
        // ball2, where $lowScorer (now non-striker) is dismissed.
        $this->ball($match, $innings, ['striker_match_player_id' => $lowScorer->id, 'non_striker_match_player_id' => $highScorer->id, 'bowler_match_player_id' => $bowler->id, 'runs_off_bat' => 5]);
        $this->ball($match, $innings, [
            'striker_match_player_id' => $highScorer->id, 'non_striker_match_player_id' => $lowScorer->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'bowled', 'dismissed_match_player_id' => $lowScorer->id,
        ]);
        // $highScorer survives at the striker end; $newBatter replaces
        // $lowScorer at the vacant non-striker end.
        $this->ball($match, $innings, ['striker_match_player_id' => $highScorer->id, 'non_striker_match_player_id' => $newBatter->id, 'bowler_match_player_id' => $bowler->id, 'runs_off_bat' => 40]);

        // A separate edition must not leak into this edition's leaderboard.
        [$matchOther, $inningsOther] = $this->newLiveMatch();
        $otherStriker = $this->selectForMatch($matchOther, $this->squadPlayer($matchOther->teamA));
        $otherNonStriker = $this->selectForMatch($matchOther, $this->squadPlayer($matchOther->teamA));
        $otherBowler = $this->selectForMatch($matchOther, $this->squadPlayer($matchOther->teamB));
        $this->ball($matchOther, $inningsOther, ['striker_match_player_id' => $otherStriker->id, 'non_striker_match_player_id' => $otherNonStriker->id, 'bowler_match_player_id' => $otherBowler->id, 'runs_off_bat' => 99]);

        $leaderboard = $this->statistics->getEditionLeaderboard($match->edition);

        $this->assertCount(2, $leaderboard['topRunScorers']);
        $this->assertSame($highScorer->teamPlayer->playerRegistration->player->id, $leaderboard['topRunScorers'][0]['player']->id);
        $this->assertSame(40, $leaderboard['topRunScorers'][0]['stats']['runs']);
        $this->assertSame(5, $leaderboard['topRunScorers'][1]['stats']['runs']);

        $this->assertCount(1, $leaderboard['topWicketTakers']);
        $this->assertSame($bowler->teamPlayer->playerRegistration->player->id, $leaderboard['topWicketTakers'][0]['player']->id);
        $this->assertSame(1, $leaderboard['topWicketTakers'][0]['stats']['wickets']);
    }

    // ----- Edition records -----

    public function test_edition_records_highest_score_best_bowling_and_most_sixes(): void
    {
        [$match, $innings] = $this->newLiveMatch();
        $teamA = $match->teamA;
        $teamB = $match->teamB;

        $bigHitter = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $otherBatter = $this->selectForMatch($match, $this->squadPlayer($teamA));
        $bowler = $this->selectForMatch($match, $this->squadPlayer($teamB));

        $this->ball($match, $innings, ['striker_match_player_id' => $bigHitter->id, 'non_striker_match_player_id' => $otherBatter->id, 'bowler_match_player_id' => $bowler->id, 'runs_off_bat' => 6]);
        $this->ball($match, $innings, ['striker_match_player_id' => $bigHitter->id, 'non_striker_match_player_id' => $otherBatter->id, 'bowler_match_player_id' => $bowler->id, 'runs_off_bat' => 6]);
        // Both sixes are even runs, so $bigHitter is still on strike —
        // $otherBatter (the non-striker) is caught here instead
        // (dismissal-end looseness already predates Phase 3.33; see
        // DeliveryService's docblock), keeping $bigHitter not out on 12.
        $this->ball($match, $innings, [
            'striker_match_player_id' => $bigHitter->id, 'non_striker_match_player_id' => $otherBatter->id, 'bowler_match_player_id' => $bowler->id,
            'runs_off_bat' => 0, 'is_wicket' => 1, 'wicket_type' => 'caught', 'dismissed_match_player_id' => $otherBatter->id, 'fielder_match_player_id' => $bowler->id,
        ]);

        $records = $this->statistics->getEditionRecords($match->edition);

        $this->assertSame(12, $records['highestScore']['runs']);
        $this->assertTrue($records['highestScore']['notOut']);
        $this->assertSame($bigHitter->teamPlayer->playerRegistration->player->id, $records['highestScore']['player']->id);

        // The bowler bowled all 3 balls of this innings: two boundary
        // balls (12 runs) plus the wicket ball — best bowling is the
        // whole-innings aggregate for that bowler, 1/12, not just the
        // wicket-taking delivery in isolation.
        $this->assertSame(1, $records['bestBowling']['wickets']);
        $this->assertSame(12, $records['bestBowling']['runsConceded']);
        $this->assertSame($bowler->teamPlayer->playerRegistration->player->id, $records['bestBowling']['player']->id);

        $this->assertSame(2, $records['mostSixes']['sixes']);
        $this->assertSame($bigHitter->teamPlayer->playerRegistration->player->id, $records['mostSixes']['player']->id);
    }
}
