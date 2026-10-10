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
        // After the real seasons (2025, 2026 ...) that tests create on purpose, and inside the 2000-2100 the form accepts, so a random year can never collide
        // with one of them (year is unique): that made roughly one run in a hundred fail.
        $year = fake()->unique()->numberBetween(2030, 2099);

        return [
            'name' => "RPPL {$year}",
            'year' => $year,
            'status' => fake()->randomElement(['upcoming', 'active', 'completed']),
        ];
    }
}
