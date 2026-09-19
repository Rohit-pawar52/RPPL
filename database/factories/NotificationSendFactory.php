<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationSend>
 */
class NotificationSendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'title_snapshot' => fake()->sentence(4),
            'message_snapshot' => fake()->paragraph(),
            'action_url_snapshot' => null,
            'attempted_count' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'sent_by' => User::factory(),
            'completed_at' => null,
        ];
    }
}
