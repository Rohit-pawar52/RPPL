<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "The public picture of the auction has changed" — a pure invalidation
 * signal, never the data. The public page reacts by fetching /auction/data
 * again (the one source of what may be shown); this carries only the
 * auction's id, nothing about any player, bid, team or amount. Same idea as
 * MatchScoreUpdated for the live scorecard.
 *
 * Queued (ShouldBroadcast) and after commit, so nothing is announced for an
 * action that was rolled back. If Reverb is not running the public page just
 * keeps polling.
 */
class AuctionUpdated implements ShouldBroadcast
{
    use Dispatchable;

    public bool $afterCommit = true;

    public function __construct(public readonly int $auctionId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('public-auction')];
    }

    /**
     * A stable name for the browser, independent of the PHP namespace.
     */
    public function broadcastAs(): string
    {
        return 'auction.updated';
    }

    /**
     * @return array{auction_id: int}
     */
    public function broadcastWith(): array
    {
        return ['auction_id' => $this->auctionId];
    }
}
