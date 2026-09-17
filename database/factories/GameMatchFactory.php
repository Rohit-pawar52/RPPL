<?php

namespace Database\Factories;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameMatch>
 *
 * Domain note: to keep team_a != team_b and both teams belonging to the
 * same edition as the match true by construction (even when this
 * factory is used standalone), edition_team_a_id/edition_team_b_id are
 * resolved via attribute closures against the already-resolved
 * edition_id, rather than each FK resolving to an unrelated random
 * record. Using closures (evaluated only for keys the caller does not
 * override) rather than eagerly creating the Edition/EditionTeams in
 * the body of definition() matters: the eager form used to create a
 * throwaway Edition (with a random status/year) and two throwaway
 * EditionTeams on every single create() call — even when the caller
 * passed its own edition_id/edition_team_a_id/edition_team_b_id — which
 * silently polluted any code querying Edition globally (e.g. "the
 * active edition") with unrelated stray rows.
 */
class GameMatchFactory extends Factory
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
            'match_number' => fake()->unique()->numberBetween(1, 100000),
            'edition_team_a_id' => fn (array $attributes) => EditionTeam::factory()->create(['edition_id' => $attributes['edition_id']])->id,
            'edition_team_b_id' => fn (array $attributes) => EditionTeam::factory()->create(['edition_id' => $attributes['edition_id']])->id,
            'venue_id' => Venue::factory(),
            'match_stage' => fake()->randomElement(['league', 'quarter_final', 'semi_final', 'final']),
            'overs_per_innings' => 20,
            'scheduled_at' => fake()->dateTimeBetween('now', '+2 months'),
            'match_status' => 'scheduled',
        ];
    }
}
