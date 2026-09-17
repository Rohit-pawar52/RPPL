<?php

namespace Database\Factories;

use App\Models\Contributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contributor>
 *
 * Default state is an unlinked, active contributor — committee_member_id
 * defaults to null rather than a random CommitteeMember, since the FK
 * is unique-when-set: a random default would risk flaky collisions
 * across factory calls in the same test. Link explicitly per-test via
 * ->state(['committee_member_id' => $member->id]) when actually needed.
 */
class ContributorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->numerify('##########'),
            'committee_member_id' => null,
            'is_active' => true,
        ];
    }
}
