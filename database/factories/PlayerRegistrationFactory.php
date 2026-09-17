<?php

namespace Database\Factories;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlayerRegistration>
 *
 * registration_number is a placeholder UUID here, NOT the real
 * RPPL-{year}-{id} format (that requires the row's own id, which
 * doesn't exist until after insert — see
 * PlayerRegistration::assignRegistrationNumber(), called by the real
 * creation paths, not by this factory). registration_number is NOT
 * NULL + UNIQUE at the DB level, so a placeholder is required here
 * purely to satisfy that constraint; a closure guarantees a fresh
 * unique value per created row. Tests that care about the real format
 * should call ->assignRegistrationNumber() explicitly after creating.
 */
class PlayerRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edition_id' => Edition::factory(),
            'player_id' => Player::factory(),
            'registration_number' => fn () => (string) Str::uuid(),
            'payment_status' => fake()->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'registration_fee' => fake()->randomFloat(2, 500, 5000),
            'registered_at' => now(),
        ];
    }
}
