<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'is_active' => true,
            'name' => fake()->name(),
            'phone' => '9'.fake()->unique()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'date_of_birth' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
            'photo_path' => null,
            'batting_style' => fake()->randomElement(['right_hand', 'left_hand']),
            'bowling_style' => fake()->randomElement(['right_arm', 'left_arm', 'none']),
            'primary_role' => fake()->randomElement(['batter', 'bowler', 'all_rounder', 'wicket_keeper']),
        ];
    }

    /**
     * Indicate that the player is no longer active (deactivated, not
     * deleted — their historical data stays intact).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
