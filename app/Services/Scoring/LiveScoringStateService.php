<?php

namespace App\Services\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\ScoringEvent;
use App\Services\Innings\InningsService;
use App\Services\LiveMatch\LiveMatchService;
use Illuminate\Support\Collection;

/**
 * Assembles the admin scorer screen's full canonical state (frozen S02
 * rules 50-61) — the single payload both the initial Blade render and
 * the JSON polling/recovery endpoint consume, so the two can never
 * drift apart (the exact same shared-render principle LiveMatchService
 * already established for the public Live Match Center).
 *
 * Every figure here is derived fresh from Delivery/ScoringEvent rows (via
 * ScorecardService, the existing scorecard engine) or from Innings'
 * already-maintained cached totals — nothing here is its own
 * independently-stored mutable statistic, per this phase's explicit
 * "do not introduce duplicated mutable truth" principle.
 */
class LiveScoringStateService
{
    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly ScorecardService $scorecards,
        private readonly LiveMatchService $liveMatch,
        private readonly InningsService $inningsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getState(GameMatch $match, Innings $innings): array
    {
        $innings = $innings->fresh(['battingTeam.team', 'bowlingTeam.team']);
        $state = $this->deliveries->expectedBattingState($innings);
        $scorecard = $this->scorecards->getInningsScorecard($innings);

        $battingByPlayerId = collect($scorecard['battingRows'])->keyBy(fn ($row) => $row['matchPlayer']->id);
        $bowlingByPlayerId = collect($scorecard['bowlingRows'])->keyBy(fn ($row) => $row['matchPlayer']->id);

        $awaitingSetup = $this->inningsService->canSetUpOpeningState($match, $innings);
        $correctableIds = $this->deliveries->correctableDeliveries($innings)->pluck('id');

        return [
            'innings' => [
                'id' => $innings->id,
                'innings_number' => $innings->innings_number,
                'status' => $innings->status,
                'total_runs' => $innings->total_runs,
                'total_wickets' => $innings->total_wickets,
                'overs_display' => $innings->oversDisplay(),
                'batting_team' => $innings->battingTeam->team->name,
                'bowling_team' => $innings->bowlingTeam->team->name,
                'crr' => $this->currentRunRate($innings),
            ],
            'awaiting_setup' => $awaitingSetup,
            'can_record_delivery' => $this->deliveries->canRecordDelivery($match, $innings),
            'is_over_limit_reached' => $this->deliveries->isOverLimitReached($match, $innings),
            'is_free_hit' => $this->deliveries->isFreeHit($innings),
            'expected_batting_state' => $state,
            'previous_over_bowler_id' => $state['awaiting_new_over_bowler'] ? $this->deliveries->bowlerOfPreviousOver($innings) : null,
            'striker' => $this->playerFigure($state['striker_id'] ?? null, $battingByPlayerId),
            'non_striker' => $this->playerFigure($state['non_striker_id'] ?? null, $battingByPlayerId),
            'bowler' => $this->bowlerFigure($state['awaiting_new_over_bowler'] ? null : ($state['bowler_id'] ?? null), $bowlingByPlayerId),
            'this_over' => $this->overStrip($innings, $correctableIds, currentOverOnly: true),
            'previous_over' => $this->overStrip($innings, $correctableIds, currentOverOnly: false),
            'chase' => $this->chaseInfo($match, $innings),
            'partnership' => $this->currentPartnership($innings),
            'last_wicket' => $this->lastWicket($innings, $battingByPlayerId, $scorecard['fallOfWickets']),
            'fall_of_wickets' => $scorecard['fallOfWickets'],
            'correctable_deliveries' => $this->correctableDeliveries($innings),
            'can_undo' => $this->deliveries->isInningsUndoable($match, $innings)
                && ($innings->deliveries()->exists() || $innings->scoringEvents()->whereIn('type', ScoringEvent::UNDOABLE_TYPES)->notUndone()->exists()),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $battingByPlayerId
     * @return array<string, mixed>|null
     */
    private function playerFigure(?int $matchPlayerId, $battingByPlayerId): ?array
    {
        if (! $matchPlayerId) {
            return null;
        }

        $row = $battingByPlayerId->get($matchPlayerId);
        $matchPlayer = $row['matchPlayer'] ?? MatchPlayer::find($matchPlayerId);

        return [
            'id' => $matchPlayerId,
            'name' => $matchPlayer?->teamPlayer->playerRegistration->player->name ?? '—',
            'runs' => $row['runs'] ?? 0,
            'balls' => $row['balls'] ?? 0,
            'fours' => $row['fours'] ?? 0,
            'sixes' => $row['sixes'] ?? 0,
            'strike_rate' => $row['strikeRate'] ?? 0.0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $bowlingByPlayerId
     * @return array<string, mixed>|null
     */
    private function bowlerFigure(?int $matchPlayerId, $bowlingByPlayerId): ?array
    {
        if (! $matchPlayerId) {
            return null;
        }

        $row = $bowlingByPlayerId->get($matchPlayerId);
        $matchPlayer = $row['matchPlayer'] ?? MatchPlayer::find($matchPlayerId);

        return [
            'id' => $matchPlayerId,
            'name' => $matchPlayer?->teamPlayer->playerRegistration->player->name ?? '—',
            'overs_display' => $row['oversDisplay'] ?? '0.0',
            'runs_conceded' => $row['runsConceded'] ?? 0,
            'wickets' => $row['wickets'] ?? 0,
            'economy' => $row['economy'] ?? 0.0,
        ];
    }

    /**
     * This Over (frozen rule 54) is every delivery in the innings'
     * current over_number (legal_balls so far / 6); Previous Over (rule
     * 55) is the one immediately before it, or null before over 2 has
     * started. Both reuse LiveMatchService::outcomeLabel() for the exact
     * same ball-outcome labels the public page shows.
     *
     * @return array{over_number: int, balls: list<array<string, mixed>>}|null
     */
    private function overStrip(Innings $innings, Collection $correctableIds, bool $currentOverOnly): ?array
    {
        $currentOverNumber = intdiv($innings->legal_balls, 6);
        $overNumber = $currentOverOnly ? $currentOverNumber : $currentOverNumber - 1;

        if ($overNumber < 0) {
            return null;
        }

        $deliveries = Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('over_number', $overNumber)
            ->orderBy('delivery_sequence')
            ->get();

        if ($deliveries->isEmpty()) {
            return null;
        }

        $latestId = (int) $correctableIds->first();

        return [
            'over_number' => $overNumber + 1,
            'balls' => $deliveries->map(fn (Delivery $d) => [
                'id' => $d->id,
                'label' => $this->liveMatch->outcomeLabel($d),
                'is_wicket' => (bool) $d->is_wicket,
                'is_correctable' => $correctableIds->contains($d->id),
                'is_latest' => $d->id === $latestId,
            ])->all(),
        ];
    }

    /**
     * Second innings only, and only while target-chasing is meaningful
     * (an overs limit must exist — frozen rule 57 says to use the
     * match's own overs_per_innings, which is nullable for an unlimited-
     * overs match; there is no well-defined "balls remaining" without
     * it, so this deliberately returns null rather than a fabricated
     * rate for that case).
     *
     * @return array<string, mixed>|null
     */
    private function chaseInfo(GameMatch $match, Innings $innings): ?array
    {
        if ((int) $innings->innings_number !== 2 || ! $match->overs_per_innings) {
            return null;
        }

        $firstInnings = Innings::query()->where('match_id', $match->id)->where('innings_number', 1)->first();

        if (! $firstInnings) {
            return null;
        }

        $target = (int) $firstInnings->total_runs + 1;
        $runsNeeded = max(0, $target - (int) $innings->total_runs);
        $ballsRemaining = max(0, ($match->overs_per_innings * 6) - (int) $innings->legal_balls);

        return [
            'target' => $target,
            'runs_needed' => $runsNeeded,
            'balls_remaining' => $ballsRemaining,
            'required_run_rate' => $ballsRemaining > 0 ? round($runsNeeded * 6 / $ballsRemaining, 2) : 0.0,
        ];
    }

    /**
     * Current run rate (frozen rule 61) — legal balls only, never
     * decimal-over arithmetic. 0.0 when no legal ball has been bowled
     * yet, rather than a division-by-zero.
     */
    private function currentRunRate(Innings $innings): float
    {
        if ($innings->legal_balls <= 0) {
            return 0.0;
        }

        return round($innings->total_runs * 6 / $innings->legal_balls, 2);
    }

    /**
     * Runs/balls scored by the batting team since the last Delivery
     * wicket (or since the start of the innings, if none has fallen yet)
     * — matches ScorecardService::getFallOfWickets()'s own existing
     * "wicket" definition (a Delivery wicket) exactly, so this and the
     * Fall of Wickets list can never disagree about when the last
     * partnership actually ended.
     *
     * @return array{runs: int, balls: int}
     */
    private function currentPartnership(Innings $innings): array
    {
        $lastWicketSequence = (int) Delivery::where('innings_id', $innings->id)
            ->where('is_wicket', true)
            ->max('delivery_sequence');

        $sinceLastWicket = Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('delivery_sequence', '>', $lastWicketSequence)
            ->get();

        return [
            'runs' => (int) $sinceLastWicket->sum('total_runs'),
            'balls' => $sinceLastWicket->filter(fn (Delivery $d) => $this->scorecards->countsAsBallFaced($d))->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $battingByPlayerId
     * @param  list<array<string, mixed>>  $fallOfWickets
     * @return array<string, mixed>|null
     */
    private function lastWicket(Innings $innings, $battingByPlayerId, array $fallOfWickets): ?array
    {
        if (empty($fallOfWickets)) {
            return null;
        }

        $last = end($fallOfWickets);

        $latestWicketDelivery = Delivery::query()
            ->where('innings_id', $innings->id)
            ->where('is_wicket', true)
            ->orderByDesc('delivery_sequence')
            ->first();

        $dismissedRow = $latestWicketDelivery ? $battingByPlayerId->get((int) $latestWicketDelivery->dismissed_match_player_id) : null;

        return [
            'player' => $last['player'],
            'runs' => $dismissedRow['runs'] ?? null,
            'balls' => $dismissedRow['balls'] ?? null,
            'team_score' => $last['score'],
            'over_notation' => $last['overNotation'],
        ];
    }

    /**
     * The latest 3 deliveries (frozen rules 43/44/46), each with the raw
     * fields a correction form needs to pre-fill, and whether it is the
     * innings' current latest delivery (only that one may have its Wide/
     * No Ball state changed — see DeliveryService::correctDelivery()).
     *
     * @return list<array<string, mixed>>
     */
    private function correctableDeliveries(Innings $innings): array
    {
        $window = $this->deliveries->correctableDeliveries($innings);

        return $window->map(fn (Delivery $d, $index) => [
            'id' => $d->id,
            'label' => "{$d->over_number}.{$d->ball_number}",
            'outcome_label' => $this->liveMatch->outcomeLabel($d),
            'is_latest' => $index === 0,
            'raw' => [
                'striker_match_player_id' => (int) $d->striker_match_player_id,
                'non_striker_match_player_id' => (int) $d->non_striker_match_player_id,
                'is_free_hit' => (bool) $d->is_free_hit,
                'is_wide' => (bool) $d->is_wide,
                'is_no_ball' => (bool) $d->is_no_ball,
                'no_ball_reason' => $d->no_ball_reason,
                'wide_running_runs' => (int) $d->wide_running_runs,
                'bye_runs' => (int) $d->bye_runs,
                'leg_bye_runs' => (int) $d->leg_bye_runs,
                'runs_off_bat' => (int) $d->runs_off_bat,
                'runs_physically_run' => $d->runs_physically_run,
                'is_short_run' => (bool) $d->is_short_run,
                'is_wicket' => (bool) $d->is_wicket,
                'wicket_type' => $d->wicket_type,
                'dismissed_match_player_id' => $d->dismissed_match_player_id,
                'fielder_match_player_id' => $d->fielder_match_player_id,
                'confirmed_survivor_end' => $d->confirmed_survivor_end,
                'commentary' => $d->commentary,
            ],
        ])->values()->all();
    }
}
