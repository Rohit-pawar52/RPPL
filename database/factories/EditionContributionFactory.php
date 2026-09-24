<?php

namespace Database\Factories;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditionContribution>
 *
 * edition_id is a lazy factory relation (resolved only if the caller
 * doesn't override it) and edition_transaction_id is an attribute
 * closure resolved against the already-resolved edition_id/amount —
 * same reasoning as the Phase 3.24 GameMatchFactory fix: eagerly
 * creating related rows in the body of definition() would create stray
 * Editions/EditionTransactions even when the caller supplies its own.
 */
class EditionContributionFactory extends Factory
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
            'contributor_id' => Contributor::factory(),
            'amount' => fake()->randomFloat(2, EditionContribution::MINIMUM_AMOUNT, 20000),
            'edition_transaction_id' => fn (array $attributes) => EditionTransaction::factory()->create([
                'edition_id' => $attributes['edition_id'],
                'type' => 'income',
                'category' => 'Contribution',
                'amount' => $attributes['amount'],
            ])->id,
            'contributed_at' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
