<?php

namespace App\Services\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use Illuminate\Support\Collection;

/**
 * Read-only cricket scorecard calculation, derived entirely from
 * Delivery rows (the authoritative scoring record) plus the Playing XI
 * (MatchPlayer). Innings.total_runs/extras/legal_balls/total_wickets
 * are only ever used for the innings headline, never for per-player
 * figures — those are always recomputed from Delivery here, so this
 * service doubles as a correctness check on the Phase 3.13 cache.
 *
 * Deliberately takes only domain models (GameMatch/Innings) and returns
 * plain arrays/collections — no Request, session, auth, or Blade
 * dependency — so a future public website controller can call the
 * exact same methods Admin\ScorecardController uses, with zero
 * duplicated calculation logic.
 */
class ScorecardService
{
    /**
     * @return list<array<string, mixed>> one entry per existing Innings, in innings_number order
     */
    public function getMatchScorecard(GameMatch $match): array
    {
        return $match->innings()
            ->orderBy('innings_number')
            ->with(['battingTeam.team', 'bowlingTeam.team'])
            ->get()
            ->map(fn (Innings $innings) => $this->getInningsScorecard($innings))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getInningsScorecard(Innings $innings): array
    {
        $deliveries = $this->loadDeliveries($innings);

        $batting = $this->getBattingScorecard($deliveries);

        return [
            'innings' => $innings,
            'battingRows' => $batting['rows'],
            'didNotBat' => $this->getDidNotBat($innings, $batting['appearedMatchPlayerIds']),
            'bowlingRows' => $this->getBowlingScorecard($deliveries),
            'extras' => $this->getExtrasBreakdown($deliveries),
            'fallOfWickets' => $this->getFallOfWickets($deliveries),
            'lastDelivery' => $this->getLastDeliveryContext($deliveries),
        ];
    }

    /**
     * @return Collection<int, Delivery>
     */
    private function loadDeliveries(Innings $innings): Collection
    {
        return Delivery::query()
            ->where('innings_id', $innings->id)
            ->with([
                'striker.teamPlayer.playerRegistration.player',
                'nonStriker.teamPlayer.playerRegistration.player',
                'bowler.teamPlayer.playerRegistration.player',
                'dismissedPlayer.teamPlayer.playerRegistration.player',
                'fielder.teamPlayer.playerRegistration.player',
            ])
            ->orderBy('delivery_sequence')
            ->get();
    }

    /**
     * Batting order follows first appearance (as striker OR
     * non-striker) in delivery_sequence order — never alphabetical,
     * MatchPlayer id, or Player id. A player who only ever appeared as
     * non-striker still counts as having batted (so they are excluded
     * from Did Not Bat), even though they accrue no runs/balls of
     * their own unless they later face a ball as striker.
     *
     * Runs/boundaries only ever come from deliveries where this player
     * was specifically the striker — byes/leg-byes/wides scored while
     * they were merely the non-striker are team extras, never credited
     * to a batter.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return array{rows: list<array<string, mixed>>, appearedMatchPlayerIds: list<int>}
     */
    private function getBattingScorecard(Collection $deliveries): array
    {
        $stats = [];
        $order = [];

        foreach ($deliveries as $delivery) {
            foreach (['striker', 'nonStriker'] as $role) {
                $matchPlayer = $delivery->{$role};

                if (! isset($stats[$matchPlayer->id])) {
                    $stats[$matchPlayer->id] = [
                        'matchPlayer' => $matchPlayer,
                        'runs' => 0,
                        'balls' => 0,
                        'fours' => 0,
                        'sixes' => 0,
                        'dismissalDelivery' => null,
                    ];
                    $order[] = $matchPlayer->id;
                }
            }

            $strikerId = $delivery->striker->id;
            $runsOffBat = (int) $delivery->runs_off_bat;

            $stats[$strikerId]['runs'] += $runsOffBat;

            if ($this->countsAsBallFaced($delivery)) {
                $stats[$strikerId]['balls']++;
            }

            if ($runsOffBat === 4) {
                $stats[$strikerId]['fours']++;
            } elseif ($runsOffBat === 6) {
                $stats[$strikerId]['sixes']++;
            }

            if ($delivery->is_wicket && $delivery->dismissed_match_player_id) {
                // Phase 3.13 requires the dismissed player to be this
                // same delivery's striker or non-striker, so the entry
                // above is guaranteed to already exist here.
                $stats[$delivery->dismissed_match_player_id]['dismissalDelivery'] = $delivery;
            }
        }

        $rows = array_map(function ($id) use ($stats) {
            $row = $stats[$id];
            $row['strikeRate'] = $row['balls'] > 0 ? round($row['runs'] / $row['balls'] * 100, 2) : 0.0;
            $row['dismissalText'] = $row['dismissalDelivery']
                ? $this->formatDismissal($row['dismissalDelivery'])
                : 'not out';

            return $row;
        }, $order);

        return ['rows' => $rows, 'appearedMatchPlayerIds' => $order];
    }

    /**
     * Whether a delivery counts as a ball faced by its striker: a
     * normal legal delivery, a bye, or a leg-bye all count; a wide or
     * no-ball does not. Computed from wide_runs/no_ball_runs directly
     * (not is_legal_delivery) — see Delivery's docblock for why.
     */
    public function countsAsBallFaced(Delivery $delivery): bool
    {
        return (int) $delivery->wide_runs === 0 && (int) $delivery->no_ball_runs === 0;
    }

    /**
     * Readable dismissal text built only from information Delivery
     * actually stores. 'caught'/'stumped' always have a fielder here —
     * StoreDeliveryRequest (Phase 3.13) requires one for those two
     * types — so no fallback is needed for them; 'run_out' is the only
     * type with a genuinely optional fielder.
     */
    public function formatDismissal(Delivery $delivery): string
    {
        $bowlerName = $delivery->bowler->teamPlayer->playerRegistration->player->name;
        $fielderName = $delivery->fielder?->teamPlayer->playerRegistration->player->name;

        return match ($delivery->wicket_type) {
            'bowled' => "b {$bowlerName}",
            'caught' => "c {$fielderName} b {$bowlerName}",
            'lbw' => "lbw b {$bowlerName}",
            'stumped' => "st {$fielderName} b {$bowlerName}",
            'hit_wicket' => "hit wicket b {$bowlerName}",
            'run_out' => $fielderName ? "run out ({$fielderName})" : 'run out',
            'obstructing_field' => 'obstructing the field',
            default => 'out',
        };
    }

    /**
     * Selected batting-team MatchPlayers who never appeared as striker
     * or non-striker at any point in this innings. Never fabricates a
     * 0-runs/0-balls batting row for them.
     *
     * @param  list<int>  $appearedMatchPlayerIds
     * @return Collection<int, MatchPlayer>
     */
    private function getDidNotBat(Innings $innings, array $appearedMatchPlayerIds): Collection
    {
        return MatchPlayer::query()
            ->where('match_id', $innings->match_id)
            ->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', $innings->batting_team_id))
            ->whereNotIn('id', $appearedMatchPlayerIds ?: [0])
            ->with('teamPlayer.playerRegistration.player')
            ->get();
    }

    /**
     * Bowling order follows first appearance (by delivery_sequence),
     * never alphabetical or id order.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return list<array<string, mixed>>
     */
    private function getBowlingScorecard(Collection $deliveries): array
    {
        $stats = [];
        $order = [];

        foreach ($deliveries as $delivery) {
            $bowler = $delivery->bowler;

            if (! isset($stats[$bowler->id])) {
                $stats[$bowler->id] = [
                    'matchPlayer' => $bowler,
                    'legalBalls' => 0,
                    'runsConceded' => 0,
                    'wickets' => 0,
                    'wideRuns' => 0,
                    'noBallRuns' => 0,
                ];
                $order[] = $bowler->id;
            }

            if ($delivery->is_legal_delivery) {
                $stats[$bowler->id]['legalBalls']++;
            }

            // Bowler-chargeable runs: runs off the bat plus wide/no-ball
            // penalty and follow-on runs. Byes, leg-byes, and penalty
            // runs are team extras, never charged to the bowler.
            $stats[$bowler->id]['runsConceded'] += (int) $delivery->runs_off_bat + (int) $delivery->wide_runs + (int) $delivery->no_ball_runs;
            $stats[$bowler->id]['wideRuns'] += (int) $delivery->wide_runs;
            $stats[$bowler->id]['noBallRuns'] += (int) $delivery->no_ball_runs;

            if ($delivery->is_wicket && $this->creditsBowlerWicket($delivery->wicket_type)) {
                $stats[$bowler->id]['wickets']++;
            }
        }

        return array_map(function ($id) use ($stats) {
            $row = $stats[$id];
            $row['oversDisplay'] = $this->formatOvers($row['legalBalls']);
            $row['economy'] = $row['legalBalls'] > 0 ? round($row['runsConceded'] * 6 / $row['legalBalls'], 2) : 0.0;

            return $row;
        }, $order);
    }

    /**
     * Bowler-credited dismissals only: bowled, caught, lbw, stumped,
     * hit_wicket. run_out and obstructing_field count toward the
     * team's Innings.total_wickets but are never credited to the
     * bowler's own figures.
     */
    public function creditsBowlerWicket(string $wicketType): bool
    {
        return in_array($wicketType, ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket'], true);
    }

    /**
     * Cricket overs notation from a legal-ball count — never decimal
     * division. Mirrors Innings::oversDisplay()'s formula, kept as its
     * own one-line method here since it operates on an arbitrary
     * per-bowler ball count, not an Innings instance.
     */
    public function formatOvers(int $legalBalls): string
    {
        return intdiv($legalBalls, 6).'.'.($legalBalls % 6);
    }

    /**
     * @param  Collection<int, Delivery>  $deliveries
     * @return array{wides: int, noBalls: int, byes: int, legByes: int, penalty: int, total: int}
     */
    private function getExtrasBreakdown(Collection $deliveries): array
    {
        $wides = $deliveries->sum(fn (Delivery $d) => (int) $d->wide_runs);
        $noBalls = $deliveries->sum(fn (Delivery $d) => (int) $d->no_ball_runs);
        $byes = $deliveries->sum(fn (Delivery $d) => (int) $d->bye_runs);
        $legByes = $deliveries->sum(fn (Delivery $d) => (int) $d->leg_bye_runs);
        $penalty = $deliveries->sum(fn (Delivery $d) => (int) $d->penalty_runs);

        return [
            'wides' => $wides,
            'noBalls' => $noBalls,
            'byes' => $byes,
            'legByes' => $legByes,
            'penalty' => $penalty,
            'total' => $wides + $noBalls + $byes + $legByes + $penalty,
        ];
    }

    /**
     * Team score at each wicket is the cumulative Delivery.total_runs
     * through and including the wicket-taking delivery itself (e.g. a
     * run-out completing a run before the throw counts that run) — not
     * Delivery.total_runs in isolation. Over notation reuses the
     * delivery's own stored over_number/ball_number (already
     * legal-ball-derived at recording time), never decimal arithmetic.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return list<array{wicketNumber: int, score: int, player: string, overNotation: string}>
     */
    private function getFallOfWickets(Collection $deliveries): array
    {
        $cumulativeRuns = 0;
        $wicketNumber = 0;
        $fallOfWickets = [];

        foreach ($deliveries as $delivery) {
            $cumulativeRuns += (int) $delivery->total_runs;

            if (! $delivery->is_wicket) {
                continue;
            }

            $wicketNumber++;

            $fallOfWickets[] = [
                'wicketNumber' => $wicketNumber,
                'score' => $cumulativeRuns,
                'player' => $delivery->dismissedPlayer->teamPlayer->playerRegistration->player->name,
                'overNotation' => $delivery->over_number.'.'.$delivery->ball_number,
            ];
        }

        return $fallOfWickets;
    }

    /**
     * Deliberately NOT "current striker" — Phase 3.13 does not persist
     * strike state, and inferring it automatically (odd runs, end of
     * over, extras, a dismissal awaiting a new batter) has enough
     * cricket nuance to get subtly wrong. This only reports who was
     * actually involved in the most recent recorded delivery, which is
     * always unambiguous.
     *
     * @param  Collection<int, Delivery>  $deliveries
     * @return array{striker: string, nonStriker: string, bowler: string}|null
     */
    private function getLastDeliveryContext(Collection $deliveries): ?array
    {
        $last = $deliveries->last();

        if (! $last) {
            return null;
        }

        return [
            'striker' => $last->striker->teamPlayer->playerRegistration->player->name,
            'nonStriker' => $last->nonStriker->teamPlayer->playerRegistration->player->name,
            'bowler' => $last->bowler->teamPlayer->playerRegistration->player->name,
        ];
    }
}
