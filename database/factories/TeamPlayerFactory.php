<?php

namespace Database\Factories;

use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamPlayer>
 *
 * Domain note: this factory does NOT verify that the generated
 * player_registration.edition_id matches the generated
 * edition_team.edition_id. Enforcing that here would require the two
 * sub-factories to share a common Edition, which adds real complexity
 * for a generic/random factory. That full relationship graph is instead
 * built explicitly, edition-by-edition, in RpplDemoSeeder.
 */
class TeamPlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edition_team_id' => EditionTeam::factory(),
            'player_registration_id' => PlayerRegistration::factory(),
            'jersey_number' => fake()->unique()->numberBetween(1, 99),
            'role' => fake()->randomElement(['batter', 'bowler', 'all_rounder', 'wicket_keeper']),
        ];
    }
}
