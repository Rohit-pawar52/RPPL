<?php

namespace Database\Factories;

use App\Models\Advertisement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Advertisement>
 *
 * media_path defaults to a plain fake string, not a stored file — tests
 * that assert on Storage::fake() build the path from a real upload.
 */
class AdvertisementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->company(),
            'tier' => Advertisement::TIER_NORMAL,
            'format' => null,
            'media_type' => Advertisement::MEDIA_IMAGE,
            'media_path' => 'ads/'.fake()->uuid().'.jpg',
            'poster_path' => null,
            'status' => 'active',
            'weight' => 1,
            'starts_on' => null,
            'ends_on' => null,
        ];
    }

    public function main(): static
    {
        return $this->state(['tier' => Advertisement::TIER_MAIN]);
    }

    public function auction(): static
    {
        return $this->state(['tier' => Advertisement::TIER_AUCTION]);
    }

    public function card(): static
    {
        return $this->state(['format' => Advertisement::FORMAT_CARD]);
    }

    public function side(): static
    {
        return $this->state(['format' => Advertisement::FORMAT_SIDE]);
    }

    public function mini(): static
    {
        return $this->state(['tier' => Advertisement::TIER_MINI]);
    }

    public function video(): static
    {
        return $this->state([
            'media_type' => Advertisement::MEDIA_VIDEO,
            'media_path' => 'ads/'.fake()->uuid().'.mp4',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
