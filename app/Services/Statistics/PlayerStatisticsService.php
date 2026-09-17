<?php

namespace App\Services\Statistics;

use App\Models\Delivery;
use App\Models\Edition;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Services\Scoring\ScorecardService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Read-only historical cricket statistics (career/edition player
 * figures, match-by-match history, edition leaderboards/records),
 * derived entirely from Delivery rows — the same authoritative source
 * ScorecardService (Phase 3.14) reads. Never an independent source of
 * truth, and never persisted to an aggregate table: for RPPL's current
 * scale, deriving on read is simple and correct, with no cache-
 * invalidation problem to manage. Persisted aggregates can be
 * introduced later only if real performance measurements justify them.
 *
 * Deliberately takes only domain models (Player/Edition) and returns
 * plain arrays/collections — no Request, session, auth, or Blade
 * dependency — so a future public website can reuse it exactly as-is.
 *
 * Cross-checked against ScorecardService by construction: the actual
 * cricket rules (ball-faced semantics, bowler-chargeable runs, bowler-
 * credited wicket types, overs notation) are not reimplemented here —
 * this service calls ScorecardService::countsAsBallFaced()/
 * creditsBowlerWicket()/formatOvers() directly, so the two can never
 * quietly disagree.
 */
class PlayerStatisticsService
{
    public function __construct(private readonly ScorecardService $scorecards) {}

    /**
     * Career statistics (all editions), or a single edition's if
     * $edition is given. "Played" means the player's MatchPlayer
     * actually appears in Delivery data for that match (as striker,
     * non-striker, bowler, dismissed player, or fielder) — not merely
     * squad selection. A player who was selected into a Playing XI but
     * never faced a ball, bowled, or was otherwise involved in a
     * recorded delivery does not count as having played that match.
     * This is the only definition the current data can back with actual
     * match/scoring history rather than mere selection.
     *
     * @return array{matches_played: int, batting: array<string, mixed>, bowling: array<string, mixed>}
     */
    public function getPlayerStatistics(Player $player, ?Edition $edition = null): array
    {
        $matchPlayerIds = $this->matchPlayerIdsForPlayer($player, $edition);

        if (empty($matchPlayerIds)) {
            return [
                'matches_played' => 0,
                'batting' => $this->emptyBattingStats(),
                'bowling' => $this->emptyBowlingStats(),
            ];
        }

        $deliveries = $this->loadDeliveriesForMatchPlayers($matchPlayerIds);

        return [
            'matches_played' => $deliveries->pluck('innings.match_id')->unique()->count(),
            'batting' => $this->computeBattingCareer($this->battingInningsBreakdown($deliveries, $matchPlayerIds)),
            'bowling' => $this->computeBowlingCareer($this->bowlingInningsBreakdown($deliveries, $matchPlayerIds)),
        ];
    }

    /**
     * Paginated, most-recent-first list of matches the player actually
     * participated in (same "played" definition as above), each with a
     * compact batting/bowling summary for that specific match. Only the
     * deliveries for the matches on the current page are loaded — the
     * player's full career delivery history is never loaded merely to
     * render one page of history.
     *
     * @return LengthAwarePaginator<int, array{match: GameMatch, batting: array<string, mixed>|null, bowling: array<string, mixed>|null}>
     */
    public function getPlayerMatchHistory(Player $player, ?Edition $edition = null, int $perPage = 10): LengthAwarePaginator
    {
        $matchPlayerIds = $this->matchPlayerIdsForPlayer($player, $edition);

        if (empty($matchPlayerIds)) {
            return $this->emptyPaginator($perPage);
        }

        $matchIds = Delivery::query()
            ->where(fn ($query) => $this->scopeAnyMatchPlayerColumn($query, $matchPlayerIds))
            ->join('innings', 'innings.id', '=', 'deliveries.innings_id')
            ->distinct()
            ->pluck('innings.match_id');

        if ($matchIds->isEmpty()) {
            return $this->emptyPaginator($perPage);
        }

        $matches = GameMatch::query()
            ->whereIn('id', $matchIds)
            ->with(['edition', 'teamA.team', 'teamB.team'])
            ->orderByDesc('scheduled_at')
            ->paginate($perPage);

        $pageMatchIds = $matches->pluck('id')->all();

        $deliveriesByMatch = Delivery::query()
            ->where(fn ($query) => $this->scopeAnyMatchPlayerColumn($query, $matchPlayerIds))
            ->whereHas('innings', fn ($query) => $query->whereIn('match_id', $pageMatchIds))
            ->with('innings:id,match_id')
            ->orderBy('innings_id')
            ->orderBy('delivery_sequence')
            ->get()
            ->groupBy(fn (Delivery $delivery) => $delivery->innings->match_id);

        $matches->getCollection()->transform(function (GameMatch $match) use ($deliveriesByMatch, $matchPlayerIds) {
            $matchDeliveries = $deliveriesByMatch->get($match->id, collect());

            return [
                'match' => $match,
                'batting' => $this->formatMatchBattingLine($this->battingInningsBreakdown($matchDeliveries, $matchPlayerIds)),
                'bowling' => $this->formatMatchBowlingLine($this->bowlingInningsBreakdown($matchDeliveries, $matchPlayerIds)),
            ];
        });

        return $matches;
    }

    /**
     * Top run scorers / wicket takers for one edition. One query for
     * this edition's selected MatchPlayers, one for this edition's
     * Delivery rows, then a single in-memory pass building per-player
     * innings breakdowns directly (not one query — or one full delivery
     * re-scan — per player), keyed by underlying Player id so a player
     * who somehow has more than one MatchPlayer in the same edition
     * still aggregates as one identity.
     *
     * @return array{topRunScorers: list<array{player: Player, stats: array<string, mixed>}>, topWicketTakers: list<array{player: Player, stats: array<string, mixed>}>}
     */
    public function getEditionLeaderboard(Edition $edition, int $limit = 5): array
    {
        $aggregates = $this->editionPlayerAggregates($edition);

        $runScorers = [];

        foreach ($aggregates['battingByPlayer'] as $playerId => $inningsBreakdown) {
            $stats = $this->computeBattingCareer($inningsBreakdown);

            if ($stats['runs'] > 0) {
                $runScorers[] = ['player' => $aggregates['playersById'][$playerId], 'stats' => $stats];
            }
        }

        $wicketTakers = [];

        foreach ($aggregates['bowlingByPlayer'] as $playerId => $inningsBreakdown) {
            $stats = $this->computeBowlingCareer($inningsBreakdown);

            if ($stats['wickets'] > 0) {
                $wicketTakers[] = ['player' => $aggregates['playersById'][$playerId], 'stats' => $stats];
            }
        }

        usort($runScorers, fn ($a, $b) => $b['stats']['runs'] <=> $a['stats']['runs']);
        usort($wicketTakers, fn ($a, $b) => $b['stats']['wickets'] <=> $a['stats']['wickets']
            ?: $a['stats']['runs_conceded'] <=> $b['stats']['runs_conceded']);

        return [
            'topRunScorers' => array_slice($runScorers, 0, $limit),
            'topWicketTakers' => array_slice($wicketTakers, 0, $limit),
        ];
    }

    /**
     * A small, explicit set of edition records — not a generalized
     * "records engine". Reuses the exact same per-player innings
     * breakdown the leaderboard uses, so these never disagree with it.
     * Highest score / best bowling are single-innings records; most
     * sixes is a whole-edition total (the conventional "Most Sixes"
     * tournament-award meaning, not a single-innings record).
     *
     * @return array{highestScore: array{player: Player, runs: int, notOut: bool}|null, bestBowling: array{player: Player, wickets: int, runsConceded: int}|null, mostSixes: array{player: Player, sixes: int}|null}
     */
    public function getEditionRecords(Edition $edition): array
    {
        $aggregates = $this->editionPlayerAggregates($edition);

        $highestScore = null;

        foreach ($aggregates['battingByPlayer'] as $playerId => $inningsBreakdown) {
            foreach ($inningsBreakdown as $stat) {
                $notOut = ! $stat['dismissed'];

                if ($highestScore === null
                    || $stat['runs'] > $highestScore['runs']
                    || ($stat['runs'] === $highestScore['runs'] && $notOut && ! $highestScore['notOut'])) {
                    $highestScore = ['player' => $aggregates['playersById'][$playerId], 'runs' => $stat['runs'], 'notOut' => $notOut];
                }
            }
        }

        $bestBowling = null;

        foreach ($aggregates['bowlingByPlayer'] as $playerId => $inningsBreakdown) {
            foreach ($inningsBreakdown as $stat) {
                if ($bestBowling === null
                    || $stat['wickets'] > $bestBowling['wickets']
                    || ($stat['wickets'] === $bestBowling['wickets'] && $stat['runsConceded'] < $bestBowling['runsConceded'])) {
                    $bestBowling = ['player' => $aggregates['playersById'][$playerId], 'wickets' => $stat['wickets'], 'runsConceded' => $stat['runsConceded']];
                }
            }
        }

        $mostSixes = null;

        foreach ($aggregates['battingByPlayer'] as $playerId => $inningsBreakdown) {
            $totalSixes = array_sum(array_column($inningsBreakdown, 'sixes'));

            if ($totalSixes > 0 && ($mostSixes === null || $totalSixes > $mostSixes['sixes'])) {
                $mostSixes = ['player' => $aggregates['playersById'][$playerId], 'sixes' => $totalSixes];
            }
        }

        return ['highestScore' => $highestScore, 'bestBowling' => $bestBowling, 'mostSixes' => $mostSixes];
    }

    /**
     * The set of MatchPlayer ids this Player identity has ever had,
     * across every PlayerRegistration/TeamPlayer they've ever held —
     * never accidentally scoped to just one registration. Optionally
     * narrowed to a single edition via the registration's own
     * edition_id (the correct edition-ownership path — PlayerRegistration,
     * not TeamPlayer or MatchPlayer, is what's actually edition-scoped).
     *
     * @return list<int>
     */
    private function matchPlayerIdsForPlayer(Player $player, ?Edition $edition): array
    {
        return MatchPlayer::query()
            ->whereHas('teamPlayer.playerRegistration', function ($query) use ($player, $edition) {
                $query->where('player_id', $player->id);

                if ($edition) {
                    $query->where('edition_id', $edition->id);
                }
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $matchPlayerIds
     */
    private function scopeAnyMatchPlayerColumn(Builder $query, array $matchPlayerIds): Builder
    {
        return $query->whereIn('striker_match_player_id', $matchPlayerIds)
            ->orWhereIn('non_striker_match_player_id', $matchPlayerIds)
            ->orWhereIn('bowler_match_player_id', $matchPlayerIds)
            ->orWhereIn('dismissed_match_player_id', $matchPlayerIds)
            ->orWhereIn('fielder_match_player_id', $matchPlayerIds);
    }

    /**
     * @param  list<int>  $matchPlayerIds
     * @return Collection<int, Delivery>
     */
    private function loadDeliveriesForMatchPlayers(array $matchPlayerIds): Collection
    {
        return Delivery::query()
            ->where(fn ($query) => $this->scopeAnyMatchPlayerColumn($query, $matchPlayerIds))
            ->with('innings:id,match_id')
            ->orderBy('innings_id')
            ->orderBy('delivery_sequence')
            ->get();
    }

    /**
     * Single-player batting breakdown: for each innings this player's
     * MatchPlayer id(s) appeared in as striker or non-striker, the raw
     * per-innings figures needed to derive every career/match batting
     * stat. Runs/balls/boundaries only ever accrue while this player
     * was specifically the striker; a bye/leg-bye/wide scored while
     * they were merely the non-striker is a team extra, never credited
     * to them.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @param  list<int>  $matchPlayerIds
     * @return array<int, array{runs: int, balls: int, fours: int, sixes: int, dismissed: bool}> keyed by innings_id
     */
    private function battingInningsBreakdown(Collection $deliveries, array $matchPlayerIds): array
    {
        $breakdown = [];

        foreach ($deliveries as $delivery) {
            $isStriker = in_array($delivery->striker_match_player_id, $matchPlayerIds, true);
            $isNonStriker = in_array($delivery->non_striker_match_player_id, $matchPlayerIds, true);

            if (! $isStriker && ! $isNonStriker) {
                continue;
            }

            $inningsId = $delivery->innings_id;
            $breakdown[$inningsId] ??= ['runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'dismissed' => false];

            if ($isStriker) {
                $runsOffBat = (int) $delivery->runs_off_bat;
                $breakdown[$inningsId]['runs'] += $runsOffBat;

                if ($this->scorecards->countsAsBallFaced($delivery)) {
                    $breakdown[$inningsId]['balls']++;
                }

                if ($runsOffBat === 4) {
                    $breakdown[$inningsId]['fours']++;
                } elseif ($runsOffBat === 6) {
                    $breakdown[$inningsId]['sixes']++;
                }
            }

            if ($delivery->is_wicket && in_array($delivery->dismissed_match_player_id, $matchPlayerIds, true)) {
                $breakdown[$inningsId]['dismissed'] = true;
            }
        }

        return $breakdown;
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     * @param  list<int>  $matchPlayerIds
     * @return array<int, array{legalBalls: int, runsConceded: int, wickets: int}> keyed by innings_id
     */
    private function bowlingInningsBreakdown(Collection $deliveries, array $matchPlayerIds): array
    {
        $breakdown = [];

        foreach ($deliveries as $delivery) {
            if (! in_array($delivery->bowler_match_player_id, $matchPlayerIds, true)) {
                continue;
            }

            $inningsId = $delivery->innings_id;
            $breakdown[$inningsId] ??= ['legalBalls' => 0, 'runsConceded' => 0, 'wickets' => 0];

            if ($delivery->is_legal_delivery) {
                $breakdown[$inningsId]['legalBalls']++;
            }

            // Bowler-chargeable runs only — byes/leg-byes/penalty are
            // team extras, never the bowler's. Identical rule to
            // ScorecardService's bowling scorecard.
            $breakdown[$inningsId]['runsConceded'] += (int) $delivery->runs_off_bat + (int) $delivery->wide_runs + (int) $delivery->no_ball_runs;

            if ($delivery->is_wicket && $this->scorecards->creditsBowlerWicket($delivery->wicket_type)) {
                $breakdown[$inningsId]['wickets']++;
            }
        }

        return $breakdown;
    }

    /**
     * One query for this edition's selected MatchPlayers (mapped to
     * their owning Player), one query for this edition's Delivery rows,
     * then a single pass building every player's batting/bowling
     * innings breakdown at once — the multi-player equivalent of
     * battingInningsBreakdown()/bowlingInningsBreakdown() above, using
     * the exact same per-delivery rules.
     *
     * @return array{battingByPlayer: array<int, array<int, array{runs:int,balls:int,fours:int,sixes:int,dismissed:bool}>>, bowlingByPlayer: array<int, array<int, array{legalBalls:int,runsConceded:int,wickets:int}>>, playersById: array<int, Player>}
     */
    private function editionPlayerAggregates(Edition $edition): array
    {
        $matchPlayers = MatchPlayer::query()
            ->whereHas('teamPlayer.playerRegistration', fn ($query) => $query->where('edition_id', $edition->id))
            ->with('teamPlayer.playerRegistration.player')
            ->get();

        if ($matchPlayers->isEmpty()) {
            return ['battingByPlayer' => [], 'bowlingByPlayer' => [], 'playersById' => []];
        }

        $playerIdByMatchPlayerId = [];
        $playersById = [];

        foreach ($matchPlayers as $matchPlayer) {
            $player = $matchPlayer->teamPlayer->playerRegistration->player;
            $playerIdByMatchPlayerId[$matchPlayer->id] = $player->id;
            $playersById[$player->id] = $player;
        }

        $deliveries = $this->loadDeliveriesForMatchPlayers($matchPlayers->pluck('id')->all());

        $battingByPlayer = [];
        $bowlingByPlayer = [];

        foreach ($deliveries as $delivery) {
            $inningsId = $delivery->innings_id;
            $strikerPlayerId = $playerIdByMatchPlayerId[$delivery->striker_match_player_id] ?? null;
            $nonStrikerPlayerId = $playerIdByMatchPlayerId[$delivery->non_striker_match_player_id] ?? null;
            $bowlerPlayerId = $playerIdByMatchPlayerId[$delivery->bowler_match_player_id] ?? null;

            if ($strikerPlayerId !== null) {
                $battingByPlayer[$strikerPlayerId][$inningsId] ??= ['runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'dismissed' => false];

                $runsOffBat = (int) $delivery->runs_off_bat;
                $battingByPlayer[$strikerPlayerId][$inningsId]['runs'] += $runsOffBat;

                if ($this->scorecards->countsAsBallFaced($delivery)) {
                    $battingByPlayer[$strikerPlayerId][$inningsId]['balls']++;
                }

                if ($runsOffBat === 4) {
                    $battingByPlayer[$strikerPlayerId][$inningsId]['fours']++;
                } elseif ($runsOffBat === 6) {
                    $battingByPlayer[$strikerPlayerId][$inningsId]['sixes']++;
                }
            }

            if ($nonStrikerPlayerId !== null) {
                $battingByPlayer[$nonStrikerPlayerId][$inningsId] ??= ['runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'dismissed' => false];
            }

            if ($delivery->is_wicket && $delivery->dismissed_match_player_id) {
                $dismissedPlayerId = $playerIdByMatchPlayerId[$delivery->dismissed_match_player_id] ?? null;

                if ($dismissedPlayerId !== null && isset($battingByPlayer[$dismissedPlayerId][$inningsId])) {
                    $battingByPlayer[$dismissedPlayerId][$inningsId]['dismissed'] = true;
                }
            }

            if ($bowlerPlayerId !== null) {
                $bowlingByPlayer[$bowlerPlayerId][$inningsId] ??= ['legalBalls' => 0, 'runsConceded' => 0, 'wickets' => 0];

                if ($delivery->is_legal_delivery) {
                    $bowlingByPlayer[$bowlerPlayerId][$inningsId]['legalBalls']++;
                }

                $bowlingByPlayer[$bowlerPlayerId][$inningsId]['runsConceded'] += (int) $delivery->runs_off_bat + (int) $delivery->wide_runs + (int) $delivery->no_ball_runs;

                if ($delivery->is_wicket && $this->scorecards->creditsBowlerWicket($delivery->wicket_type)) {
                    $bowlingByPlayer[$bowlerPlayerId][$inningsId]['wickets']++;
                }
            }
        }

        return ['battingByPlayer' => $battingByPlayer, 'bowlingByPlayer' => $bowlingByPlayer, 'playersById' => $playersById];
    }

    /**
     * Aggregates a per-innings batting breakdown into career/edition
     * figures. Batting average uses dismissals (innings actually got
     * out), never innings_batted — a not-out innings must never be
     * treated as a dismissal for averaging purposes. Highest score
     * ties are broken in favour of the not-out innings, so the
     * displayed highest score correctly carries its "*" when applicable.
     *
     * @param  array<int, array{runs:int,balls:int,fours:int,sixes:int,dismissed:bool}>  $inningsBreakdown
     * @return array<string, mixed>
     */
    private function computeBattingCareer(array $inningsBreakdown): array
    {
        if (empty($inningsBreakdown)) {
            return $this->emptyBattingStats();
        }

        $runs = array_sum(array_column($inningsBreakdown, 'runs'));
        $ballsFaced = array_sum(array_column($inningsBreakdown, 'balls'));
        $inningsBatted = count($inningsBreakdown);
        $notOuts = count(array_filter($inningsBreakdown, fn ($stat) => ! $stat['dismissed']));
        $dismissals = $inningsBatted - $notOuts;

        $highestScore = null;
        $highestScoreNotOut = false;

        foreach ($inningsBreakdown as $stat) {
            $notOut = ! $stat['dismissed'];

            if ($highestScore === null || $stat['runs'] > $highestScore || ($stat['runs'] === $highestScore && $notOut && ! $highestScoreNotOut)) {
                $highestScore = $stat['runs'];
                $highestScoreNotOut = $notOut;
            }
        }

        return [
            'innings_batted' => $inningsBatted,
            'runs' => $runs,
            'balls_faced' => $ballsFaced,
            'highest_score' => $highestScore,
            'highest_score_not_out' => $highestScoreNotOut,
            'batting_average' => $dismissals > 0 ? round($runs / $dismissals, 2) : null,
            'strike_rate' => $ballsFaced > 0 ? round($runs / $ballsFaced * 100, 2) : 0.0,
            'fours' => array_sum(array_column($inningsBreakdown, 'fours')),
            'sixes' => array_sum(array_column($inningsBreakdown, 'sixes')),
            'not_outs' => $notOuts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBattingStats(): array
    {
        return [
            'innings_batted' => 0,
            'runs' => 0,
            'balls_faced' => 0,
            'highest_score' => null,
            'highest_score_not_out' => false,
            'batting_average' => null,
            'strike_rate' => 0.0,
            'fours' => 0,
            'sixes' => 0,
            'not_outs' => 0,
        ];
    }

    /**
     * @param  array<int, array{legalBalls:int,runsConceded:int,wickets:int}>  $inningsBreakdown
     * @return array<string, mixed>
     */
    private function computeBowlingCareer(array $inningsBreakdown): array
    {
        if (empty($inningsBreakdown)) {
            return $this->emptyBowlingStats();
        }

        $legalBalls = array_sum(array_column($inningsBreakdown, 'legalBalls'));
        $runsConceded = array_sum(array_column($inningsBreakdown, 'runsConceded'));
        $wickets = array_sum(array_column($inningsBreakdown, 'wickets'));

        // Best bowling: most wickets wins; tied on wickets, fewer runs
        // conceded wins. Maidens are deliberately not modeled — Phase
        // 3.14 omitted them for the same reason (see its report) and
        // that decision is unchanged here.
        $best = null;

        foreach ($inningsBreakdown as $stat) {
            if ($best === null
                || $stat['wickets'] > $best['wickets']
                || ($stat['wickets'] === $best['wickets'] && $stat['runsConceded'] < $best['runsConceded'])) {
                $best = $stat;
            }
        }

        return [
            'innings_bowled' => count($inningsBreakdown),
            'legal_balls' => $legalBalls,
            'overs' => $this->scorecards->formatOvers($legalBalls),
            'runs_conceded' => $runsConceded,
            'wickets' => $wickets,
            'bowling_average' => $wickets > 0 ? round($runsConceded / $wickets, 2) : null,
            'economy' => $legalBalls > 0 ? round($runsConceded * 6 / $legalBalls, 2) : 0.0,
            'best_bowling' => $best ? "{$best['wickets']}/{$best['runsConceded']}" : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBowlingStats(): array
    {
        return [
            'innings_bowled' => 0,
            'legal_balls' => 0,
            'overs' => '0.0',
            'runs_conceded' => 0,
            'wickets' => 0,
            'bowling_average' => null,
            'economy' => 0.0,
            'best_bowling' => null,
        ];
    }

    /**
     * A single match has at most one innings where this player's team
     * batted, so the breakdown has at most one entry — no aggregation
     * needed, just formatting it (or nothing, if they didn't bat).
     *
     * @param  array<int, array{runs:int,balls:int,fours:int,sixes:int,dismissed:bool}>  $battingBreakdown
     * @return array{runs: int, balls: int, not_out: bool}|null
     */
    private function formatMatchBattingLine(array $battingBreakdown): ?array
    {
        if (empty($battingBreakdown)) {
            return null;
        }

        $stat = reset($battingBreakdown);

        return ['runs' => $stat['runs'], 'balls' => $stat['balls'], 'not_out' => ! $stat['dismissed']];
    }

    /**
     * @param  array<int, array{legalBalls:int,runsConceded:int,wickets:int}>  $bowlingBreakdown
     * @return array{wickets: int, runs_conceded: int, overs: string}|null
     */
    private function formatMatchBowlingLine(array $bowlingBreakdown): ?array
    {
        if (empty($bowlingBreakdown)) {
            return null;
        }

        $stat = reset($bowlingBreakdown);

        return [
            'wickets' => $stat['wickets'],
            'runs_conceded' => $stat['runsConceded'],
            'overs' => $this->scorecards->formatOvers($stat['legalBalls']),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{match: GameMatch, batting: array<string, mixed>|null, bowling: array<string, mixed>|null}>
     */
    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, Paginator::resolveCurrentPage(), [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }
}
