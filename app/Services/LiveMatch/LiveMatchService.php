<?php

namespace App\Services\LiveMatch;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;

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
     * @return array{match_status: string, match_result: string|null, should_poll: bool, innings: list<array<string, mixed>>, recent_deliveries: list<array<string, mixed>>}
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
    private function outcomeLabel(Delivery $delivery): string
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
