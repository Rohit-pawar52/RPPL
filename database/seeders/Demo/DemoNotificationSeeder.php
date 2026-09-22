<?php

namespace Database\Seeders\Demo;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A small set of demo notification MASTERS only — deliberately zero
 * NotificationSend history (Phase B5). Seeding fake "accepted" counts
 * would misrepresent a real Firebase result that never happened; a
 * stakeholder can see/create/edit these through the real admin UI, and
 * a genuine Send/Resend can be demonstrated once Firebase is actually
 * configured, going through the real pipeline rather than a fabricated
 * one.
 */
class DemoNotificationSeeder extends Seeder
{
    /**
     * @var list<array{title: string, message: string, action_url: string|null}>
     */
    private const NOTIFICATIONS = [
        [
            'title' => 'Player Registrations Open',
            'message' => 'Player registrations for RPPL are now open.',
            'action_url' => '/player-registration',
        ],
        [
            'title' => 'RPPL Match Reminder',
            'message' => "Today's RPPL match starts at 7:00 PM.",
            'action_url' => '/matches',
        ],
        [
            'title' => 'RPPL Final Result Announced',
            'message' => 'The RPPL final result is now available on the website.',
            'action_url' => null,
        ],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@rppl.test')->first();

        if (! $admin) {
            return;
        }

        foreach (self::NOTIFICATIONS as $notification) {
            Notification::firstOrCreate(
                ['title' => $notification['title']],
                [
                    'message' => $notification['message'],
                    'action_url' => $notification['action_url'],
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
