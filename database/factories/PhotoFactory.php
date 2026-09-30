<?php

namespace Database\Factories;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 *
 * photo_path defaults to a plain fake string, not a real stored file.
 */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(12),
            'photo_path' => 'photos/'.fake()->uuid().'.jpg',
            'status' => 'active',
            'priority' => 100,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
