<?php

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditionTeam>
 */
class EditionTeamFactory extends Factory
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
            'team_id' => Team::factory(),
        ];
    }
}
