<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Same-match/same-team consistency (team_player must belong to one of
 * the two edition_teams participating in this match) is enforced in
 * Phase 3.10 by StoreMatchPlayerRequest, not here — see its docblock
 * and the Phase 3.10 report.
 */
class MatchPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'team_player_id',
        'is_captain',
        'is_wicket_keeper',
    ];

    protected function casts(): array
    {
        return [
            'is_captain' => 'boolean',
            'is_wicket_keeper' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class);
    }

    /**
     * Explicit, named inverse relationships to Delivery — used only for
     * the MatchPlayerService scoring-history check, not for general
     * querying. A bare "deliveries()" name would be ambiguous since
     * deliveries reference match_players through five different columns.
     */
    public function deliveriesAsStriker(): HasMany
    {
        return $this->hasMany(Delivery::class, 'striker_match_player_id');
    }

    public function deliveriesAsNonStriker(): HasMany
    {
        return $this->hasMany(Delivery::class, 'non_striker_match_player_id');
    }

    public function deliveriesAsBowler(): HasMany
    {
        return $this->hasMany(Delivery::class, 'bowler_match_player_id');
    }

    public function deliveriesAsDismissedPlayer(): HasMany
    {
        return $this->hasMany(Delivery::class, 'dismissed_match_player_id');
    }

    public function deliveriesAsFielder(): HasMany
    {
        return $this->hasMany(Delivery::class, 'fielder_match_player_id');
    }
}
