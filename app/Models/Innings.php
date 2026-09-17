<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * batting_team_id/bowling_team_id are derived from the parent match's
 * toss (Innings #1) or reversed from Innings #1 (Innings #2) by
 * InningsService — see its docblocks and the Phase 3.12 report. They
 * are never independently editable through any admin form.
 *
 * legal_balls/total_runs/total_wickets/extras are caches rebuilt from
 * Delivery rows by DeliveryService::recalculateInningsTotals() (Phase
 * 3.13) after every scoring change — never written independently. Once
 * both Innings #1 and #2 are completed, MatchResultService (Phase
 * 3.15) derives the match's final result from these same totals.
 */
class Innings extends Model
{
    use HasFactory;

    protected $table = 'innings';

    /**
     * Standard limited-overs cricket: ten wickets end an innings. Also
     * used by MatchResultService's wickets-remaining margin calculation
     * — centralized here rather than duplicated as a magic number.
     */
    public const MAX_WICKETS = 10;

    protected $fillable = [
        'match_id',
        'innings_number',
        'batting_team_id',
        'bowling_team_id',
        'status',
        'legal_balls',
        'total_runs',
        'total_wickets',
        'extras',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function battingTeam(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'batting_team_id');
    }

    public function bowlingTeam(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'bowling_team_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Cricket overs notation from legal_balls (6 legal balls per over,
     * a fixed cricket rule — not derived from overs_per_innings, which
     * caps innings length rather than balls-per-over). E.g. 7 legal
     * balls -> "1.1", never the naive decimal "1.17".
     */
    public function oversDisplay(): string
    {
        return intdiv($this->legal_balls, 6).'.'.($this->legal_balls % 6);
    }
}
