<?php

namespace App\Policies;

use App\Models\Auction;
use App\Models\User;

/**
 * The player auction is run by the admin and the auctioneer — both can do
 * everything in it (set it up, run it, override, reopen, complete) — and by
 * nobody else. A scorer has no auction access, and an auctioneer has no
 * access to anything outside the auction.
 */
class AuctionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canRun($user);
    }

    public function view(User $user, Auction $auction): bool
    {
        return $this->canRun($user);
    }

    public function create(User $user): bool
    {
        return $this->canRun($user);
    }

    public function update(User $user, Auction $auction): bool
    {
        return $this->canRun($user);
    }

    private function canRun(User $user): bool
    {
        return in_array($user->role?->slug, ['admin', 'auctioneer'], true);
    }
}
