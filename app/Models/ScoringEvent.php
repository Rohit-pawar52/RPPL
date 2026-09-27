<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only audit record for a scoring-adjacent action that is
 * deliberately not a Delivery — see the scoring_events migration's
 * docblock for the full list of event types and why they share one
 * table. Never updated after creation.
 */
class ScoringEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_PENALTY_RUNS = 'penalty_runs';

    public const TYPE_RETIRED_HURT = 'retired_hurt';

    public const TYPE_RETIRED_OUT = 'retired_out';

    public const TYPE_CHANGE_STRIKE = 'change_strike';

    public const TYPE_BOWLER_CHANGE_MID_OVER = 'bowler_change_mid_over';

    public const TYPE_NEW_BATTER_SELECTED = 'new_batter_selected';

    public const TYPE_OVER_BOWLER_SELECTED = 'over_bowler_selected';

    public const TYPE_MANUAL_INNINGS_COMPLETION = 'manual_innings_completion';

    public const TYPE_INNINGS_REOPENED = 'innings_reopened';

    public const TYPE_MATCH_RESULT_REOPENED = 'match_result_reopened';

    public const TYPE_SUPER_OVER_RESULT = 'super_over_result';

    /**
     * Event types Universal Undo (frozen rule 42) may reverse. Lifecycle
     * events (manual completion, innings/match reopen, Super Over
     * result) are deliberately excluded — each already has its own
     * dedicated, explicit reopen/re-record action, and folding them into
     * generic undo would duplicate that existing architecture rather
     * than reuse it.
     *
     * @var list<string>
     */
    public const UNDOABLE_TYPES = [
        self::TYPE_PENALTY_RUNS,
        self::TYPE_RETIRED_HURT,
        self::TYPE_RETIRED_OUT,
        self::TYPE_CHANGE_STRIKE,
        self::TYPE_BOWLER_CHANGE_MID_OVER,
        self::TYPE_NEW_BATTER_SELECTED,
        self::TYPE_OVER_BOWLER_SELECTED,
    ];

    protected $fillable = [
        'match_id',
        'innings_id',
        'type',
        'match_player_id',
        'awarded_team_id',
        'runs',
        'payload',
        'reason',
        'performed_by',
        'action_sequence',
        'undone_at',
        'undone_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function scopeNotUndone($query)
    {
        return $query->whereNull('undone_at');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function innings(): BelongsTo
    {
        return $this->belongsTo(Innings::class);
    }

    public function matchPlayer(): BelongsTo
    {
        return $this->belongsTo(MatchPlayer::class);
    }

    public function awardedTeam(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'awarded_team_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function undoneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }
}
