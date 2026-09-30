<?php

namespace Database\Factories;

use App\Models\News;
use App\Models\NewsImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsImage>
 *
 * image_path is a plain fake string, not a real stored file.
 */
class NewsImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'news_id' => News::factory(),
            'image_path' => 'news/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }
}
