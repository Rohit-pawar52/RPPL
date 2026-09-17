<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 *
 * Generates generic team names. Recognizable IPL-style team names are
 * intentionally NOT hard-coded here — that is what RpplDemoSeeder is for.
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(2, true)).' Cricket Club',
            'short_name' => strtoupper(fake()->unique()->lexify('???')),
            'logo_path' => null,
        ];
    }
}
