<?php

namespace App\Services\LiveMatch;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Services\Scoring\DeliveryService;
use App\Services\Scoring\ScorecardService;
use Illuminate\Support\Collection;

/**
 * Assembles the public Live Match Center payload — shared verbatim by
 * the initial Blade render and the JSON polling endpoint, so the two
 * can never drift apart. Purely presentational: it never recalculates
 * innings totals or cricket rules. Headline score comes from Innings'
 * own cached total_runs/total_wickets/legal_balls (via oversDisplay()) —
 * exactly what DeliveryService already maintains — and the recent-ball
 * feed comes from Delivery rows, formatted for display only (ball
 * label, outcome label, commentary). It never determines legality,
 * wicket validity, or bowler-chargeable runs — those rules live only in
 * DeliveryService/ScorecardService and are never duplicated here.
 */
class LiveMatchService
{
    private const RECENT_DELIVERIES_LIMIT = 30;

    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly ScorecardService $scorecards,
    ) {}

    /**
     * Whether the Live Match Center is available at all for this match.
     * Driven by actual scoring data existing — the same Phase 3.18
     * principle ("availability follows scoring data, not blindly the
     * status field") — never solely by match_status.
     */
    public function isAvailable(GameMatch $match): bool
    {
        return $match->innings()->exists();
    }

    /**
     * Whether the public page should actively poll for updates.
     * Deliberately status-driven (unlike isAvailable() above): the real
     * Phase 3.18 dev-data finding — an Innings existing on a match still
     * marked 'scheduled' — must never be auto-polled, since the match
     * isn't actually live regardless of what historical data exists.
     */
    public function shouldPoll(GameMatch $match): bool
    {
        return $match->match_status === 'live';
    }

    /**
     * @return array{match_status: string, match_result: string|null, should_poll: bool, innings: list<array<string, mixed>>, recent_deliveries: list<array<string, mixed>>, chase: array<string, mixed>|null, board: array<string, mixed>|null}
     */
    public function getLiveMatchData(GameMatch $match): array
    {
        $innings = $match->innings()
            ->with(['battingTeam.team', 'bowlingTeam.team'])
            ->orderBy('innings_number')
            ->get();

        $primaryInnings = $innings->last();

        return [
            'match_status' => $match->match_status,
            'match_result' => $match->match_status === 'completed' ? $match->match_result : null,
            'should_poll' => $this->shouldPoll($match),
            'innings' => $innings->map(fn (Innings $i) => $this->formatInnings($i))->all(),
            'recent_deliveries' => $primaryInnings ? $this->recentDeliveries($primaryInnings) : [],
            'chase' => $this->chaseInfo($match, $innings),
            'board' => $primaryInnings ? $this->board($primaryInnings) : null,
        ];
    }

    /**
     * Who is batting and bowling right now, as a live score page shows it: the two batters at the crease (the one on
     * strike first) with runs, balls, fours, sixes and strike rate, the bowler of the over in progress and the one
     * before with their figures, the current partnership and the last wicket. Only while the innings is live, and only
     * ever read from the same scorecard engine the Scorecard tab uses, never calculated a second way.
     *
     * @return array{batters: list<array<string, mixed>>, bowlers: list<array<string, mixed>>, partnership: array{runs: int, balls: int}, last_wicket: array<string, mixed>|null}|null
     */
    private function board(Innings $innings): ?array
    {
        if ($innings->status !== 'live') {
            return null;
        }

        $state = $this->deliveries->expectedBattingState($innings);
        $card = $this->scorecards->getInningsScorecard($innings);

        $batting = collect($card['battingRows'])->keyBy(fn (array $row) => $row['matchPlayer']->id);
        $bowling = collect($card['bowlingRows'])->keyBy(fn (array $row) => $row['matchPlayer']->id);

        $batters = [];

        foreach ([[$state['striker_id'] ?? null, true], [$state['non_striker_id'] ?? null, false]] as [$id, $onStrike]) {
            if (! $id) {
                continue;
            }

            $row = $batting->get((int) $id);
            $matchPlayer = $row['matchPlayer'] ?? MatchPlayer::with('teamPlayer.playerRegistration.player')->find($id);

            $batters[] = [
                'name' => $matchPlayer?->teamPlayer->playerRegistration->player->name ?? '—',
                'runs' => $row['runs'] ?? 0,
                'balls' => $row['balls'] ?? 0,
                'fours' => $row['fours'] ?? 0,
                'sixes' => $row['sixes'] ?? 0,
                'strike_rate' => $row['strikeRate'] ?? 0.0,
                'on_strike' => $onStrike,
            ];
        }

        // The bowler of the over in progress, then the one before: the two latest different bowlers.
        $recentBowlerIds = Delivery::query()
            ->where('innings_id', $innings->id)
            ->orderByDesc('delivery_sequence')
            ->limit(self::RECENT_DELIVERIES_LIMIT)
            ->pluck('bowler_match_player_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(2)
            ->values();

        $currentBowlerId = ! ($state['awaiting_new_over_bowler'] ?? false) ? (int) ($state['bowler_id'] ?? 0) : 0;

        $bowlers = $recentBowlerIds
            ->map(function (int $id) use ($bowling, $currentBowlerId) {
                $row = $bowling->get($id);

                return $row ? [
                    'name' => $row['matchPlayer']->teamPlayer->playerRegistration->player->name,
                    'overs' => $row['oversDisplay'],
                    'runs' => $row['runsConceded'],
                    'wickets' => $row['wickets'],
                    'economy' => $row['economy'],
                    'current' => $id === $currentBowlerId,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'batters' => $batters,
            'bowlers' => $bowlers,
            'partnership' => $this->partnership($innings),
            'last_wicket' => $this->lastWicket($card, $batting),
        ];
    }

    /**
     * Runs and balls since the last wicket fell.
     *
     * @return array{runs: int, balls: int}
     */
    private function partnership(Innings $innings): array
    {
        $lastWicketSequence = (int) Delivery::where('innings_id', $innings->id)->where('is_wicket', true)->max('delivery_sequence');

        $since = Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('delivery_sequence', '>', $lastWicketSequence)
            ->get();

        return [
            'runs' => (int) $since->sum('total_runs'),
            'balls' => $since->filter(fn (Delivery $d) => $this->scorecards->countsAsBallFaced($d))->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  Collection<int, array<string, mixed>>  $batting
     * @return array<string, mixed>|null
     */
    private function lastWicket(array $card, $batting): ?array
    {
        $fall = $card['fallOfWickets'];

        if ($fall === []) {
            return null;
        }

        $last = end($fall);
        $row = $batting->first(fn (array $r) => $r['matchPlayer']->teamPlayer->playerRegistration->player->name === $last['player'] && $r['dismissalDelivery']);

        return [
            'player' => $last['player'],
            'runs' => $row['runs'] ?? null,
            'balls' => $row['balls'] ?? null,
            'team_score' => $last['score'],
            'wickets' => $last['wicketNumber'],
            'over' => $last['overNotation'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInnings(Innings $innings): array
    {
        return [
            'innings_number' => $innings->innings_number,
            'batting_team' => $innings->battingTeam->team->name,
            'bowling_team' => $innings->bowlingTeam->team->name,
            'status' => $innings->status,
            'total_runs' => $innings->total_runs,
            'total_wickets' => $innings->total_wickets,
            'overs_display' => $innings->oversDisplay(),
            // Current run rate — legal balls only, never decimal-over
            // arithmetic. 0.0 when no legal ball has been bowled yet,
            // rather than a division-by-zero.
            'crr' => $innings->legal_balls > 0 ? round($innings->total_runs * 6 / $innings->legal_balls, 2) : 0.0,
        ];
    }

    /**
     * Target/runs-needed/balls-remaining/required-run-rate for a live
     * second innings — null whenever a chase isn't currently meaningful
     * (no second innings yet, it's not live, or the match has no fixed
     * overs limit to derive "balls remaining" from). Same formula
     * LiveScoringStateService already uses for the admin scorer screen,
     * duplicated here (not shared) because that service is admin-only
     * and carries admin-specific concerns (correction eligibility, etc.)
     * this public payload must never depend on.
     *
     * @param  Collection<int, Innings>  $innings
     * @return array<string, mixed>|null
     */
    private function chaseInfo(GameMatch $match, $innings): ?array
    {
        if (! $match->overs_per_innings) {
            return null;
        }

        $first = $innings->firstWhere('innings_number', 1);
        $second = $innings->firstWhere('innings_number', 2);

        if (! $first || ! $second || $second->status !== 'live') {
            return null;
        }

        $target = (int) $first->total_runs + 1;
        $runsNeeded = max(0, $target - (int) $second->total_runs);
        $ballsRemaining = max(0, ($match->overs_per_innings * 6) - (int) $second->legal_balls);

        return [
            'target' => $target,
            'runs_needed' => $runsNeeded,
            'balls_remaining' => $ballsRemaining,
            'required_run_rate' => $ballsRemaining > 0 ? round($runsNeeded * 6 / $ballsRemaining, 2) : 0.0,
        ];
    }

    /**
     * Latest deliveries first (delivery_sequence DESC — the
     * authoritative chronological key from Phase 3.13, never
     * over_number/ball_number, which may legitimately repeat for
     * consecutive illegal deliveries). Bounded to a recent window; the
     * full scorecard remains the place for complete history.
     *
     * @return list<array<string, mixed>>
     */
    private function recentDeliveries(Innings $innings): array
    {
        return Delivery::query()
            ->where('innings_id', $innings->id)
            ->with([
                'striker.teamPlayer.playerRegistration.player',
                'bowler.teamPlayer.playerRegistration.player',
                'dismissedPlayer.teamPlayer.playerRegistration.player',
                'fielder.teamPlayer.playerRegistration.player',
            ])
            ->orderByDesc('delivery_sequence')
            ->limit(self::RECENT_DELIVERIES_LIMIT)
            ->get()
            ->map(fn (Delivery $delivery) => $this->formatDelivery($delivery))
            ->all();
    }

    /**
     * Presentation-only single-delivery formatting: a compact ball
     * label (the delivery's own stored over_number.ball_number — never
     * renumbered), a compact outcome label, and commentary (the
     * scorer's own stored text, or a concise generated fallback — never
     * written back to the database). Only public-safe player names are
     * included (see the class docblock for why no phone/email/etc. is
     * ever touched here — those fields simply aren't read).
     *
     * @return array<string, mixed>
     */
    public function formatDelivery(Delivery $delivery): array
    {
        return [
            'ball_label' => "{$delivery->over_number}.{$delivery->ball_number}",
            'outcome_label' => $this->outcomeLabel($delivery),
            'commentary' => $delivery->commentary ?: $this->fallbackCommentary($delivery),
            'striker' => $delivery->striker->teamPlayer->playerRegistration->player->name,
            'bowler' => $delivery->bowler->teamPlayer->playerRegistration->player->name,
            'is_wicket' => $delivery->is_wicket,
            'dismissed_player' => $delivery->is_wicket && $delivery->dismissedPlayer
                ? $delivery->dismissedPlayer->teamPlayer->playerRegistration->player->name
                : null,
        ];
    }

    /**
     * A wicket always takes label priority ("W") over whatever extra
     * happened on the same ball — the extra detail still appears in the
     * commentary text. Extras are mutually exclusive by schema
     * invariant (Phase 3.13), so this is a plain priority ladder, not a
     * combination engine.
     */
    /**
     * Public so LiveScoringStateService's This Over/Previous Over strips
     * (frozen S02 rules 54/55) can reuse the exact same ball-outcome
     * label the public Live Match Center already shows — never a second,
     * independently-maintained formatting implementation.
     */
    public function outcomeLabel(Delivery $delivery): string
    {
        if ($delivery->is_wicket) {
            return 'W';
        }

        if ((int) $delivery->wide_runs > 0) {
            return $delivery->wide_runs > 1 ? "{$delivery->wide_runs}Wd" : 'Wd';
        }

        if ((int) $delivery->no_ball_runs > 0) {
            return (int) $delivery->runs_off_bat > 0 ? "Nb+{$delivery->runs_off_bat}" : 'Nb';
        }

        if ((int) $delivery->bye_runs > 0) {
            return $delivery->bye_runs > 1 ? "{$delivery->bye_runs}B" : 'B';
        }

        if ((int) $delivery->leg_bye_runs > 0) {
            return $delivery->leg_bye_runs > 1 ? "{$delivery->leg_bye_runs}LB" : 'LB';
        }

        return (string) $delivery->runs_off_bat;
    }

    /**
     * Only used when the scorer left commentary blank. A concise,
     * deterministic sentence built from the same columns outcomeLabel()
     * reads — display logic only, never persisted.
     */
    private function fallbackCommentary(Delivery $delivery): string
    {
        $striker = $delivery->striker->teamPlayer->playerRegistration->player->name;
        $bowler = $delivery->bowler->teamPlayer->playerRegistration->player->name;
        $prefix = "{$bowler} to {$striker}, ";

        $extraLabel = null;

        if ((int) $delivery->wide_runs > 0) {
            $extraLabel = 'wide';
        } elseif ((int) $delivery->no_ball_runs > 0) {
            $extraLabel = 'no ball';
        } elseif ((int) $delivery->bye_runs > 0) {
            $extraLabel = $delivery->bye_runs.' bye'.($delivery->bye_runs === 1 ? '' : 's');
        } elseif ((int) $delivery->leg_bye_runs > 0) {
            $extraLabel = $delivery->leg_bye_runs.' leg bye'.($delivery->leg_bye_runs === 1 ? '' : 's');
        }

        $runs = (int) $delivery->runs_off_bat;
        $runsLabel = match ($runs) {
            0 => 'no run',
            4 => 'FOUR',
            6 => 'SIX',
            default => "{$runs} run".($runs === 1 ? '' : 's'),
        };

        $parts = [];

        if ($extraLabel) {
            $parts[] = $extraLabel;
        } elseif (! $delivery->is_wicket || $runs > 0) {
            // Skip the redundant "no run," immediately before a wicket
            // call — "OUT — bowled." reads better than "no run, OUT —
            // bowled." for the overwhelmingly common 0-run dismissal.
            $parts[] = $runsLabel;
        }

        if ($delivery->is_wicket) {
            $parts[] = 'OUT — '.str_replace('_', ' ', $delivery->wicket_type);
        }

        return $prefix.implode(', ', $parts).'.';
    }
}
