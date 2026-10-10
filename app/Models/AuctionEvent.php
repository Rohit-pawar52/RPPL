<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an auction's activity log (see the create_auction_events_table migration). Written by
 * AuctionService inside the same transaction as the change it records, so the log can never disagree with
 * what happened. Never edited or deleted by the application.
 */
class AuctionEvent extends Model
{
    public const UPDATED_AT = null;

    public const CALLED = 'called';

    public const BID = 'bid';

    public const BID_UNDONE = 'bid_undone';

    public const BID_TEAM_CHANGED = 'bid_team_changed';

    public const SOLD = 'sold';

    public const HELD = 'held';

    public const RELEASED = 'released';

    public const SALE_REOPENED = 'sale_reopened';

    public const TAKEN_BACK = 'taken_back';

    public const PRICE_CHANGED = 'price_changed';

    public const PLAYER_EDITED = 'player_edited';

    public const WALK_IN = 'walk_in';

    public const POOL = 'pool';

    public const STARTED = 'started';

    public const PAUSED = 'paused';

    public const RESUMED = 'resumed';

    public const NEXT_ROUND = 'next_round';

    public const SETTINGS = 'settings';

    public const COMPLETED = 'completed';

    public const RESET = 'reset';

    public const REOPENED = 'reopened';

    protected $fillable = [
        'auction_id',
        'auction_lot_id',
        'user_id',
        'type',
        'player_name',
        'team_name',
        'amount',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * The line as a person reads it, in the viewer's language (only the facts are stored, never the sentence).
     */
    public function describe(): string
    {
        $player = $this->player_name ?? '—';
        $team = $this->team_name ?? '—';
        $amount = $this->amount !== null ? __(':amount pts', ['amount' => points($this->amount)]) : '—';

        return match ($this->type) {
            self::CALLED => __(':player was called to the block.', ['player' => $player]),
            self::BID => $this->note === 'override'
                ? __(':team bid :amount for :player (over the reserve limit).', ['team' => $team, 'amount' => $amount, 'player' => $player])
                : __(':team bid :amount for :player.', ['team' => $team, 'amount' => $amount, 'player' => $player]),
            self::BID_UNDONE => __('The bid of :amount by :team for :player was undone.', ['amount' => $amount, 'team' => $team, 'player' => $player]),
            self::BID_TEAM_CHANGED => __('The bid of :amount for :player was moved to :team.', ['amount' => $amount, 'player' => $player, 'team' => $team]),
            self::SOLD => __(':player was sold to :team for :amount.', ['player' => $player, 'team' => $team, 'amount' => $amount]),
            self::HELD => __(':player was put on hold.', ['player' => $player]),
            self::RELEASED => __(':player was put back among the waiting players.', ['player' => $player]),
            self::SALE_REOPENED => __('The sale of :player was reopened.', ['player' => $player]),
            self::TAKEN_BACK => __(':player was taken back from their team and is waiting again.', ['player' => $player]),
            self::PRICE_CHANGED => __('The price of :player (:team) was changed to :amount.', ['player' => $player, 'team' => $team, 'amount' => $amount]),
            self::PLAYER_EDITED => __('The details of :player were corrected.', ['player' => $player]),
            self::WALK_IN => __(':player was added as a walk-in player.', ['player' => $player]),
            self::POOL => __('The player pool was updated (:change).', ['change' => $this->note ?? '']),
            self::STARTED => __('The auction was started.'),
            self::PAUSED => __('The auction was paused.'),
            self::RESUMED => __('The auction was resumed.'),
            self::NEXT_ROUND => __('Round :round started, :count players came back.', ['round' => $this->note ?? '', 'count' => $this->amount ?? 0]),
            self::SETTINGS => $this->note === 'team_purse'
                ? __('The purse of :team was changed to :amount.', ['team' => $team, 'amount' => $amount])
                : __('The auction rules were changed.'),
            self::COMPLETED => __('The auction was completed; :count players were left unsold.', ['count' => $this->amount ?? 0]),
            self::RESET => __('The auction was reset for a fresh start.'),
            self::REOPENED => __('The auction was reopened; :count players came back to waiting.', ['count' => $this->amount ?? 0]),
            default => $this->type,
        };
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
