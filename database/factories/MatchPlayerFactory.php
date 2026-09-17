<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchPlayer>
 *
 * Domain note: this factory does NOT verify that the generated
 * team_player belongs to one of the generated match's two participating
 * edition_teams — doing so here would require pulling in the match's
 * whole team graph. That full relationship graph is instead built
 * explicitly in RpplDemoSeeder.
 */
class MatchPlayerFactory extends Factory
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
            'team_player_id' => TeamPlayer::factory(),
            'is_captain' => false,
            'is_wicket_keeper' => false,
        ];
    }
}
