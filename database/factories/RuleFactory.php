<?php

namespace Database\Factories;

use App\Models\Rule;
use App\Models\RuleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 *
 * image_path defaults to null (no image) — tests that need an actual
 * stored image build it via RuleService + Storage::fake() themselves.
 */
class RuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rule_type_id' => RuleType::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'image_path' => null,
            'sort_order' => 100,
            'status' => 'active',
            'is_important' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function important(): static
    {
        return $this->state(['is_important' => true]);
    }
}
