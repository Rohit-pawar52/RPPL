<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'content' => fake()->paragraph(),
            'status' => 'active',
            'priority' => 100,
            'published_at' => now()->subHour(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function scheduled(): static
    {
        return $this->state(['published_at' => now()->addDay()]);
    }
}
