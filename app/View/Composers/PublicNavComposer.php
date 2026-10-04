<?php

namespace App\View\Composers;

use App\Models\Auction;
use App\Models\Edition;
use App\Services\Auction\AuctionStateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Shares the "current" edition with the public header — bound only to
 * layouts.partials.public-header, so this one extra query never runs
 * for admin/guest/maintenance pages. Used solely to link the header's
 * "Points Table" item at the current edition's own page (which already
 * renders full standings) without inventing a new route.
 *
 * Also shares the public auction's status ('live', 'paused', 'completed' or
 * null) so the header can link to it — kept for a couple of seconds, since
 * every page of every visitor asks.
 */
class PublicNavComposer
{
    public function __construct(private readonly AuctionStateService $auctions) {}

    public function compose(View $view): void
    {
        $view->with('currentEditionForNav', Edition::current());
        $view->with('auctionForNav', $this->auctionStatus());
    }

    private function auctionStatus(): ?string
    {
        $seconds = (int) config('auction.public_cache_seconds');
        $find = fn () => $this->auctions->publicAuction()?->status;

        if ($seconds <= 0) {
            return $find();
        }

        // A string is cached, so "no auction" is remembered as '' (null would
        // be read as a miss).
        return Cache::remember(Auction::PUBLIC_STATUS_CACHE_KEY, $seconds, fn () => $find() ?? '') ?: null;
    }
}
