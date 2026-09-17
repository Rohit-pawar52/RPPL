<?php

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditionTransaction>
 */
class EditionTransactionFactory extends Factory
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
            'type' => fake()->randomElement(EditionTransaction::TYPES),
            'category' => fake()->randomElement(['Sponsorship', 'Ground fee', 'Equipment', 'Umpire fee', null]),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
