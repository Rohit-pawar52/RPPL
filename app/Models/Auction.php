<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The player auction of one edition. Teams bid "points" (never rupees) for
 * the edition's paid registrations; a sale puts the player in the team's
 * squad (TeamPlayer.sold_amount), which stays the one source of truth for
 * who plays for whom.
 *
 * The rule values (purse, minimum bid, step, squad size) are plain
 * settings, read fresh on every action — changing one applies from the next
 * bid and never reaches back to a player who is already sold. All the
 * rules live in AuctionService.
 */
class Auction extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_LIVE = 'live';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LIVE,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
    ];

    protected $fillable = [
        'edition_id',
        'status',
        'team_purse',
        'min_bid',
        'bid_step',
        'min_squad',
        'max_squad',
        'show_live_bids',
        'round',
        'current_lot_id',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'team_purse' => 'integer',
            'min_bid' => 'integer',
            'bid_step' => 'integer',
            'min_squad' => 'integer',
            'max_squad' => 'integer',
            'show_live_bids' => 'boolean',
            'round' => 'integer',
            'current_lot_id' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(AuctionLot::class);
    }

    public function currentLot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'current_lot_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isLive(): bool
    {
        return $this->status === self::STATUS_LIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Bids and sales are only taken while the auction is live.
     */
    public function acceptsBids(): bool
    {
        return $this->isLive();
    }

    /**
     * The purse a team starts with: its own, or the auction's default.
     */
    public function purseFor(EditionTeam $editionTeam): int
    {
        return (int) ($editionTeam->auction_purse ?? $this->team_purse);
    }
}
