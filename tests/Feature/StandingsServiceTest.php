<?php

namespace Tests\Feature;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Team;
use App\Services\Statistics\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deliberately lives outside tests/Feature/Admin — StandingsService has
 * no admin/HTTP dependency (see its docblock), and these tests exercise
 * it directly, the same way a future public-website consumer would.
 *
 * Builds GameMatch rows with already-finalized result fields directly
 * (never via the scoring pipeline) — StandingsService must consume
 * Phase 3.15's stored result, never recalculate it, so these tests
 * deliberately never touch Delivery/Innings at all.
 */
class StandingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StandingsService $standings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->standings = app(StandingsService::class);
    }

    /**
     * @return array{0: Edition, 1: EditionTeam, 2: EditionTeam}
     */
    private function editionWithTwoTeams(): array
    {
        $edition = Edition::factory()->create();
        $teamA = EditionTeam::factory()->create(['edition_id' => $edition->id]);
        $teamB = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        return [$edition, $teamA, $teamB];
    }

    private function match(EditionTeam $teamA, EditionTeam $teamB, array $overrides = []): GameMatch
    {
        return GameMatch::factory()->create(array_merge([
            'edition_id' => $teamA->edition_id,
            'edition_team_a_id' => $teamA->id,
            'edition_team_b_id' => $teamB->id,
        ], $overrides));
    }

    private function rowFor(array $standings, EditionTeam $editionTeam): array
    {
        return collect($standings['standings'])->first(fn ($row) => $row['edition_team']->id === $editionTeam->id);
    }

    // ----- Basic eligibility -----

    public function test_every_edition_team_appears_and_only_completed_matches_count(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $teamC = EditionTeam::factory()->create(['edition_id' => $edition->id]); // zero matches

        $this->match($teamA, $teamB, ['match_status' => 'scheduled', 'result_type' => null, 'winner_team_id' => null]);
        $this->match($teamA, $teamB, ['match_status' => 'live', 'result_type' => null, 'winner_team_id' => null]);
        $this->match($teamA, $teamB, ['match_status' => 'cancelled', 'result_type' => 'won', 'winner_team_id' => $teamA->id]);

        $standings = $this->standings->getEditionStandings($edition);

        $this->assertCount(3, $standings['standings']);
        $this->assertSame(0, $standings['ignored_matches_count']);

        foreach ([$teamA, $teamB, $teamC] as $team) {
            $row = $this->rowFor($standings, $team);
            $this->assertSame(0, $row['played']);
            $this->assertSame(0, $row['points']);
        }
    }

    // ----- Win/loss -----

    public function test_completed_won_result_awards_winner_and_loser_correctly(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $teamA->id]);

        $standings = $this->standings->getEditionStandings($edition);

        $winnerRow = $this->rowFor($standings, $teamA);
        $this->assertSame(1, $winnerRow['played']);
        $this->assertSame(1, $winnerRow['won']);
        $this->assertSame(0, $winnerRow['lost']);
        $this->assertSame(StandingsService::WIN_POINTS, $winnerRow['points']);

        $loserRow = $this->rowFor($standings, $teamB);
        $this->assertSame(1, $loserRow['played']);
        $this->assertSame(1, $loserRow['lost']);
        $this->assertSame(0, $loserRow['won']);
        $this->assertSame(0, $loserRow['points']);
    }

    // ----- Tie -----

    public function test_completed_tied_result_awards_both_teams(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'tied', 'winner_team_id' => null]);

        $standings = $this->standings->getEditionStandings($edition);

        foreach ([$teamA, $teamB] as $team) {
            $row = $this->rowFor($standings, $team);
            $this->assertSame(1, $row['played']);
            $this->assertSame(1, $row['tied']);
            $this->assertSame(StandingsService::TIE_POINTS, $row['points']);
        }
    }

    // ----- No result -----

    public function test_completed_no_result_awards_both_teams(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'no_result', 'winner_team_id' => null]);

        $standings = $this->standings->getEditionStandings($edition);

        foreach ([$teamA, $teamB] as $team) {
            $row = $this->rowFor($standings, $team);
            $this->assertSame(1, $row['played']);
            $this->assertSame(1, $row['no_result']);
            $this->assertSame(StandingsService::NO_RESULT_POINTS, $row['points']);
        }
    }

    // ----- Abandoned excluded -----

    public function test_abandoned_result_is_excluded_from_standings(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'abandoned', 'winner_team_id' => null]);

        $standings = $this->standings->getEditionStandings($edition);

        $this->assertSame(1, $standings['ignored_matches_count']);
        $this->assertSame(0, $this->rowFor($standings, $teamA)['played']);
        $this->assertSame(0, $this->rowFor($standings, $teamB)['played']);
    }

    // ----- Multiple matches -----

    public function test_multiple_results_aggregate_correctly(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $teamC = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $teamA->id]);
        $this->match($teamA, $teamC, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $teamC->id]);
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'tied', 'winner_team_id' => null]);

        $standings = $this->standings->getEditionStandings($edition);

        $teamARow = $this->rowFor($standings, $teamA);
        $this->assertSame(3, $teamARow['played']);
        $this->assertSame(1, $teamARow['won']);
        $this->assertSame(1, $teamARow['lost']);
        $this->assertSame(1, $teamARow['tied']);
        // 1 win + 1 loss + 1 tie = WIN_POINTS + LOSS_POINTS + TIE_POINTS.
        $this->assertSame(StandingsService::WIN_POINTS + StandingsService::TIE_POINTS, $teamARow['points']);
    }

    // ----- Ranking -----

    public function test_standings_are_ranked_by_points_then_wins_then_team_name(): void
    {
        $edition = Edition::factory()->create();
        $teamZebra = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => Team::factory()->create(['name' => 'Zebra FC'])->id]);
        $teamAlpha = EditionTeam::factory()->create(['edition_id' => $edition->id, 'team_id' => Team::factory()->create(['name' => 'Alpha FC'])->id]);
        $teamHigh = EditionTeam::factory()->create(['edition_id' => $edition->id]);

        // teamHigh: 1 win (2 pts) — clear leader.
        $this->match($teamHigh, $teamZebra, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $teamHigh->id]);

        // teamZebra and teamAlpha both end on 1 point (a tie each) with
        // equal wins (0) — team name must break the tie alphabetically.
        $this->match($teamZebra, $teamAlpha, ['match_status' => 'completed', 'result_type' => 'tied', 'winner_team_id' => null]);

        $standings = $this->standings->getEditionStandings($edition)['standings'];

        $this->assertSame($teamHigh->id, $standings[0]['edition_team']->id);
        $this->assertSame(1, $standings[0]['position']);

        // Alpha FC before Zebra FC despite Zebra being created first.
        $this->assertSame('Alpha FC', $standings[1]['edition_team']->team->name);
        $this->assertSame(2, $standings[1]['position']);
        $this->assertSame('Zebra FC', $standings[2]['edition_team']->team->name);
        $this->assertSame(3, $standings[2]['position']);
    }

    // ----- Lifecycle / active status -----

    public function test_inactive_team_still_appears_with_historical_results(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $teamA->id]);

        $teamA->team->update(['is_active' => false]);

        $standings = $this->standings->getEditionStandings($edition);

        $row = $this->rowFor($standings, $teamA);
        $this->assertSame(1, $row['played']);
        $this->assertSame(1, $row['won']);
    }

    // ----- Edition isolation -----

    public function test_another_editions_matches_never_affect_this_table(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        [$otherEdition, $otherTeamA, $otherTeamB] = $this->editionWithTwoTeams();
        $this->match($otherTeamA, $otherTeamB, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $otherTeamA->id]);

        $standings = $this->standings->getEditionStandings($edition);

        $this->assertCount(2, $standings['standings']);
        $this->assertSame(0, $this->rowFor($standings, $teamA)['played']);
    }

    // ----- Integrity -----

    public function test_won_result_with_invalid_winner_is_ignored_safely(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $unrelatedTeam = EditionTeam::factory()->create();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $unrelatedTeam->id]);

        $standings = $this->standings->getEditionStandings($edition);

        $this->assertSame(1, $standings['ignored_matches_count']);
        $this->assertSame(0, $this->rowFor($standings, $teamA)['played']);
        $this->assertSame(0, $this->rowFor($standings, $teamB)['played']);
    }

    public function test_tied_result_with_non_null_winner_is_ignored_safely(): void
    {
        [$edition, $teamA, $teamB] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamB, ['match_status' => 'completed', 'result_type' => 'tied', 'winner_team_id' => $teamA->id]);

        $standings = $this->standings->getEditionStandings($edition);

        $this->assertSame(1, $standings['ignored_matches_count']);
        $this->assertSame(0, $this->rowFor($standings, $teamA)['played']);
    }

    public function test_same_team_on_both_sides_is_ignored_safely_and_does_not_crash_the_page(): void
    {
        [$edition, $teamA] = $this->editionWithTwoTeams();
        $this->match($teamA, $teamA, ['match_status' => 'completed', 'result_type' => 'won', 'winner_team_id' => $teamA->id]);

        $standings = $this->standings->getEditionStandings($edition);

        $this->assertSame(1, $standings['ignored_matches_count']);
        $this->assertSame(0, $this->rowFor($standings, $teamA)['played']);
    }
}
