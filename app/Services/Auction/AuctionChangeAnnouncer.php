<?php

namespace App\Services\Auction;

use App\Events\AuctionUpdated;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Tells the public page that the auction has changed. It is wired to the
 * auction, lot and bid models (see AppServiceProvider), so any change to
 * them — whoever makes it — is announced, and nothing is announced for a
 * change that was rolled back: the work waits for the surrounding database
 * transaction to commit, and a burst of saves inside one action is
 * announced once.
 *
 * Announcing means two things: the short-lived copy of the public data is
 * dropped (so the very next request sees the new picture, not a stale one),
 * and a Reverb "something changed" signal goes out (AuctionUpdated). If
 * Reverb is down the public page just keeps polling, so a failure here is
 * reported and otherwise ignored.
 */
class AuctionChangeAnnouncer
{
    /** @var array<int, true> */
    private array $scheduled = [];

    public function changed(Model $model): void
    {
        $auctionId = match (true) {
            $model instanceof Auction => $model->id,
            $model instanceof AuctionLot => $model->auction_id,
            $model instanceof AuctionBid => $model->lot?->auction_id,
            default => null,
        };

        if ($auctionId === null || isset($this->scheduled[$auctionId])) {
            return;
        }

        $this->scheduled[$auctionId] = true;

        DB::afterCommit(function () use ($auctionId) {
            unset($this->scheduled[$auctionId]);

            try {
                Cache::forget(Auction::publicStateCacheKey($auctionId));
                Cache::forget(Auction::PUBLIC_STATUS_CACHE_KEY);

                event(new AuctionUpdated($auctionId));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
