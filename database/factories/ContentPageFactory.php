<?php

namespace Database\Factories;

use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentPage>
 *
 * Defaults to the Privacy Policy type — real tests override `type`
 * explicitly (one of ContentPage::TYPES) when the specific canonical
 * page matters.
 */
class ContentPageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ContentPage::TYPE_PRIVACY_POLICY,
            'title' => ContentPage::DEFAULT_TITLES[ContentPage::TYPE_PRIVACY_POLICY],
            'content' => fake()->paragraphs(3, true),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
