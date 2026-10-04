<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'edition_id' => Edition::factory(),
            'status' => Auction::STATUS_DRAFT,
            'team_purse' => 600000,
            'min_bid' => 500,
            'bid_step' => 500,
            'min_squad' => 12,
            'max_squad' => 15,
            'show_live_bids' => true,
            'round' => 1,
        ];
    }

    public function live(): static
    {
        return $this->state(['status' => Auction::STATUS_LIVE, 'started_at' => now()]);
    }
}
