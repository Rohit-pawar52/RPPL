<?php

namespace Database\Factories;

use App\Models\Edition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Edition>
 */
class EditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2020, 2099);

        return [
            'name' => "RPPL {$year}",
            'year' => $year,
            'status' => fake()->randomElement(['upcoming', 'active', 'completed']),
        ];
    }
}
