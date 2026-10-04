<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One player in an auction and where they stand: waiting (pending), on the
 * block (live), bought (sold), put aside to come back in a later round
 * (hold) or left over at the end (unsold). `version` goes up on every
 * change so an action taken on an out-of-date screen can be refused.
 */
class AuctionLot extends Model
{
    use HasFactory;

    public const PENDING = 'pending';

    public const LIVE = 'live';

    public const SOLD = 'sold';

    public const HOLD = 'hold';

    public const UNSOLD = 'unsold';

    public const STATUSES = [self::PENDING, self::LIVE, self::SOLD, self::HOLD, self::UNSOLD];

    protected $fillable = [
        'auction_id',
        'player_registration_id',
        'status',
        'round',
        'current_bid',
        'leading_edition_team_id',
        'team_player_id',
        'version',
        'called_at',
        'sold_at',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'current_bid' => 'integer',
            'version' => 'integer',
            'called_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function playerRegistration(): BelongsTo
    {
        return $this->belongsTo(PlayerRegistration::class);
    }

    public function leadingTeam(): BelongsTo
    {
        return $this->belongsTo(EditionTeam::class, 'leading_edition_team_id');
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    public function scopeStatus(Builder $query, string ...$statuses): Builder
    {
        return $query->whereIn('status', $statuses);
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE;
    }

    public function isSold(): bool
    {
        return $this->status === self::SOLD;
    }
}
