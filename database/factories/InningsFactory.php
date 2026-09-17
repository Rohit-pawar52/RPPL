<?php

namespace Database\Factories;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Innings>
 *
 * Domain note: this factory does NOT verify that batting_team_id and
 * bowling_team_id are the two edition_teams actually participating in
 * the generated match. That full relationship graph is instead built
 * explicitly in RpplDemoSeeder.
 */
class InningsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'innings_number' => 1,
            'batting_team_id' => EditionTeam::factory(),
            'bowling_team_id' => EditionTeam::factory(),
            'status' => 'scheduled',
            'legal_balls' => 0,
            'total_runs' => 0,
            'total_wickets' => 0,
            'extras' => 0,
        ];
    }
}
