<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bid in an auction. A bid that is undone is stamped cancelled_at, never
 * deleted, so the history of the auction stays complete; the standing bid on
 * a player is the latest one that is not cancelled.
 */
class AuctionBid extends Model
{
    use HasFactory;

    protected $fillable = [
        'auction_lot_id',
        'edition_team_id',
        'amount',
        'user_id',
        'idempotency_key',
        'is_override',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_override' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'auction_lot_id');
    }

    public function editionTeam(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeStanding(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }
}
