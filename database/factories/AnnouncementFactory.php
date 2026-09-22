<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 *
 * Defaults to an indefinitely-active announcement (no start/end
 * boundary) — real tests override starts_at/ends_at/is_active
 * explicitly to exercise scopeActive()'s specific rules.
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message' => fake()->sentence(6),
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'sort_order' => 0,
            'created_by' => User::factory(),
        ];
    }
}
