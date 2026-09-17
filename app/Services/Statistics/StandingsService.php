<?php

namespace App\Services\Statistics;

use App\Models\Edition;
use App\Models\GameMatch;

/**
 * Read-only tournament standings (points table), derived entirely from
 * already-finalized GameMatch result fields — never recalculated from
 * Innings/Delivery data. Phase 3.15's MatchResultService is the single
 * source of truth for what happened in a match (result_type,
 * winner_team_id); this service only folds those already-decided
 * outcomes into a per-team points table:
 *
 *   Delivery -> Innings totals -> MatchResultService -> finalized
 *   result -> StandingsService -> standings
 *
 * No standings/points table is persisted — deriving on read avoids a
 * synchronization problem (a match result changing without the points
 * table being updated to match). Can be revisited with a materialized
 * aggregate later if real performance measurements justify it.
 *
 * Deliberately takes only an Edition and returns a plain array — no
 * Request, session, auth, or Blade dependency — so a future public
 * website can reuse it as-is.
 */
class StandingsService
{
    /**
     * This project has no established points-system rule anywhere else
     * (searched before adding these) — this is the conventional simple
     * system for this phase, centralized here rather than scattered as
     * magic numbers. Documented as an application rule that can be made
     * configurable later if RPPL adopts a different points system.
     */
    public const WIN_POINTS = 2;

    public const TIE_POINTS = 1;

    public const NO_RESULT_POINTS = 1;

    public const LOSS_POINTS = 0;

    /**
     * One row per EditionTeam belonging to this edition (including
     * teams with zero completed matches), ranked by points descending,
     * then wins descending, then team name ascending — a deliberately
     * temporary tiebreak until Net Run Rate is implemented as its own
     * carefully-defined feature. Never approximated here with a naive
     * runs/overs calculation.
     *
     * Only matches with match_status === 'completed' count. Among
     * those, only result_type in [won, tied, no_result] are counted —
     * 'abandoned' is deliberately excluded (no points rule for
     * abandonment has been established anywhere in this project), and
     * any match whose stored result fields are internally inconsistent
     * (winner not one of the two participating teams, a tie/no-result
     * with a non-null winner, a team reference outside this edition,
     * team A === team B) is skipped rather than guessed at, with the
     * count of such skipped matches reported back for admin visibility.
     *
     * @return array{standings: list<array<string, mixed>>, ignored_matches_count: int}
     */
    public function getEditionStandings(Edition $edition): array
    {
        $editionTeams = $edition->editionTeams()->with('team')->get();

        $rows = [];

        foreach ($editionTeams as $editionTeam) {
            $rows[$editionTeam->id] = [
                'edition_team' => $editionTeam,
                'played' => 0,
                'won' => 0,
                'lost' => 0,
                'tied' => 0,
                'no_result' => 0,
                'points' => 0,
            ];
        }

        $matches = GameMatch::query()
            ->where('edition_id', $edition->id)
            ->where('match_status', 'completed')
            ->get(['id', 'edition_team_a_id', 'edition_team_b_id', 'result_type', 'winner_team_id']);

        $ignoredMatchesCount = 0;

        foreach ($matches as $match) {
            $teamAId = $match->edition_team_a_id;
            $teamBId = $match->edition_team_b_id;

            if ($teamAId === $teamBId || ! isset($rows[$teamAId]) || ! isset($rows[$teamBId])) {
                $ignoredMatchesCount++;

                continue;
            }

            switch ($match->result_type) {
                case 'won':
                    if ((int) $match->winner_team_id === (int) $teamAId) {
                        $winnerId = $teamAId;
                        $loserId = $teamBId;
                    } elseif ((int) $match->winner_team_id === (int) $teamBId) {
                        $winnerId = $teamBId;
                        $loserId = $teamAId;
                    } else {
                        // winner_team_id is null or references neither
                        // participating team — corrupt for standings
                        // purposes. Never guess a winner.
                        $ignoredMatchesCount++;

                        continue 2;
                    }

                    $rows[$winnerId]['played']++;
                    $rows[$winnerId]['won']++;
                    $rows[$winnerId]['points'] += self::WIN_POINTS;

                    $rows[$loserId]['played']++;
                    $rows[$loserId]['lost']++;
                    $rows[$loserId]['points'] += self::LOSS_POINTS;

                    break;

                case 'tied':
                    if ($match->winner_team_id !== null) {
                        $ignoredMatchesCount++;

                        continue 2;
                    }

                    foreach ([$teamAId, $teamBId] as $teamId) {
                        $rows[$teamId]['played']++;
                        $rows[$teamId]['tied']++;
                        $rows[$teamId]['points'] += self::TIE_POINTS;
                    }

                    break;

                case 'no_result':
                    if ($match->winner_team_id !== null) {
                        $ignoredMatchesCount++;

                        continue 2;
                    }

                    foreach ([$teamAId, $teamBId] as $teamId) {
                        $rows[$teamId]['played']++;
                        $rows[$teamId]['no_result']++;
                        $rows[$teamId]['points'] += self::NO_RESULT_POINTS;
                    }

                    break;

                default:
                    // 'abandoned' (and any other/unset result_type on a
                    // completed match) has no established points rule —
                    // excluded rather than treated as a no-result guess.
                    $ignoredMatchesCount++;

                    break;
            }
        }

        $standings = array_values($rows);

        usort($standings, fn ($a, $b) => $b['points'] <=> $a['points']
            ?: $b['won'] <=> $a['won']
            ?: $a['edition_team']->team->name <=> $b['edition_team']->team->name);

        foreach ($standings as $index => &$row) {
            $row['position'] = $index + 1;
        }

        return ['standings' => $standings, 'ignored_matches_count' => $ignoredMatchesCount];
    }
}
