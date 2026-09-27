<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ball-by-ball scoring record — the authoritative source of truth for
 * an innings' score. Innings.total_runs/extras/legal_balls/total_wickets
 * are caches rebuilt from these rows by
 * DeliveryService::recalculateInningsTotals(); they are never an
 * independent source of truth. See DeliveryService's docblocks and the
 * Phase 3.13 report for the full validation/legality/wicket rules
 * enforced when a Delivery is created (participant eligibility,
 * is_legal_delivery derivation, wicket/extra combinations, sequencing).
 *
 * Batter "balls faced" (Phase 3.14, ScorecardService::countsAsBallFaced())
 * is keyed off wide_runs/no_ball_runs directly, not is_legal_delivery —
 * for this schema's supported extras the two currently coincide, but
 * they are computed independently so that stays true by design, not
 * by coincidence, if a future extra type is ever added.
 */
class Delivery extends Model
{
    /**
     * Must match the enum values in the deliveries migration exactly.
     */
    public const WICKET_TYPES = ['bowled', 'caught', 'lbw', 'stumped', 'hit_wicket', 'run_out', 'obstructing_field'];

    /**
     * Retained as the enumeration of dismissal types only. The old
     * single-select "extra_type" request/UI concept (wide/no_ball/bye/
     * leg_bye as one mutually-exclusive choice) is gone as of S02 —
     * is_wide/is_no_ball plus the always-independent bye_runs/
     * leg_bye_runs columns replace it, because a no-ball combined with
     * byes/leg-byes on the same delivery is legal cricket and a single
     * extra_type could never represent it.
     */
    protected $fillable = [
        'innings_id',
        'delivery_sequence',
        'over_number',
        'ball_number',
        'striker_match_player_id',
        'non_striker_match_player_id',
        'bowler_match_player_id',
        'runs_off_bat',
        'wide_runs',
        'wide_running_runs',
        'no_ball_runs',
        'bye_runs',
        'leg_bye_runs',
        'penalty_runs',
        'total_runs',
        'is_legal_delivery',
        'is_free_hit',
        'no_ball_reason',
        'is_wide',
        'is_no_ball',
        'is_wicket',
        'is_short_run',
        'runs_physically_run',
        'wicket_type',
        'dismissed_match_player_id',
        'fielder_match_player_id',
        'confirmed_survivor_end',
        'commentary',
        'is_edited',
        'edit_reason',
        'idempotency_key',
        'action_sequence',
    ];

    protected function casts(): array
    {
        return [
            'is_legal_delivery' => 'boolean',
            'is_free_hit' => 'boolean',
            'is_wide' => 'boolean',
            'is_no_ball' => 'boolean',
            'is_wicket' => 'boolean',
            'is_short_run' => 'boolean',
            'is_edited' => 'boolean',
        ];
    }

    public function innings(): BelongsTo
    {
        return $this->belongsTo(Innings::class);
    }

    public function striker(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class, 'striker_match_player_id');
    }

    public function nonStriker(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class, 'non_striker_match_player_id');
    }

    public function bowler(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class, 'bowler_match_player_id');
    }

    public function dismissedPlayer(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class, 'dismissed_match_player_id');
    }

    public function fielder(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class, 'fielder_match_player_id');
    }
}
