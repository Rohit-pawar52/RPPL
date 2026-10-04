<?php

namespace Database\Factories;

use App\Models\Auction;
use App\Models\AuctionLot;
use App\Models\PlayerRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionLot>
 */
class AuctionLotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'player_registration_id' => PlayerRegistration::factory(),
            'status' => AuctionLot::PENDING,
            'round' => 1,
            'version' => 0,
        ];
    }
}
