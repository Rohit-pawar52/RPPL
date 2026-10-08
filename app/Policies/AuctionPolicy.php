<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;

/**
 * The player auction belongs to whoever holds the `auction.run` permission (the admin and the
 * auctioneer by default): they can do everything in it (set it up, run it, override, reopen,
 * complete) and nobody else can see any of it. The permission covers the auction alone, so an
 * auctioneer gets nothing outside it.
 */
class AuctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('auction.run');
    }

    public function view(User $user, Auction $auction): bool
    {
        return $user->hasPermission('auction.run');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('auction.run');
    }

    public function update(User $user, Auction $auction): bool
    {
        return $user->hasPermission('auction.run');
    }
}
