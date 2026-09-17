<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Represents a row in the `matches` table.
 *
 * Named GameMatch (not Match) because `match` is a reserved keyword in
 * PHP 8+ and cannot be used as a class name.
 *
 * Same-edition/team-eligibility consistency (team_a/team_b must belong
 * to this match's edition_id, team_a != team_b) is enforced in Phase 3.9
 * by StoreGameMatchRequest/UpdateGameMatchRequest, not here — see their
 * docblocks and the Phase 3.9 report.
 */
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    /**
     * Must match the enum values in the matches migration exactly.
     */
    public const STATUSES = ['scheduled', 'toss', 'live', 'completed', 'abandoned', 'cancelled'];

    public const STAGES = ['league', 'quarter_final', 'semi_final', 'final'];

    public const TOSS_DECISIONS = ['bat', 'bowl'];

    protected $fillable = [
        'edition_id',
        'match_number',
        'edition_team_a_id',
        'edition_team_b_id',
        'venue_id',
        'match_stage',
        'overs_per_innings',
        'scheduled_at',
        'started_at',
        'completed_at',
        'match_status',
        'toss_winner_team_id',
        'toss_decision',
        'match_result',
        'winner_team_id',
        'result_type',
        'win_margin_type',
        'win_margin',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function teamA(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'edition_team_a_id');
    }

    public function teamB(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'edition_team_b_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function tossWinner(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'toss_winner_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'winner_team_id');
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function innings(): HasMany
    {
        return $this->hasMany(Innings::class, 'match_id');
    }

    /**
     * Explicit, named single-innings relations — used by InningsService
     * and the match show page, which both repeatedly need "the" first
     * or second innings rather than the whole innings() collection.
     */
    public function firstInnings(): HasOne
    {
        return $this->hasOne(Innings::class, 'match_id')->where('innings_number', 1);
    }

    public function secondInnings(): HasOne
    {
        return $this->hasOne(Innings::class, 'match_id')->where('innings_number', 2);
    }
}
