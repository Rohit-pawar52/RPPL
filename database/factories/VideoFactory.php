<?php

namespace Database\Factories;

use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 *
 * video_path/thumbnail_path default to plain fake strings, not real
 * stored files — tests that need Storage::fake() assertions build the
 * path themselves via an actual upload.
 */
class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(12),
            'video_path' => 'videos/'.fake()->uuid().'.mp4',
            'thumbnail_path' => null,
            'status' => 'active',
            'priority' => 100,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
