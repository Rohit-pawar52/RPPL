<?php

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 *
 * Generic random content by default — real tests override group/key/
 * value/type explicitly to exercise a specific SettingsRegistry entry,
 * exactly like every other factory in this project.
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'general',
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
            'type' => Setting::TYPE_STRING,
        ];
    }
}
