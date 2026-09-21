<?php

namespace Database\Seeders\Demo;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A small, deterministic set of demo announcements for the public
 * ticker (Phase 3.45) — no relative dates (which would go stale), so
 * every seeded row here is indefinitely active (no starts_at/ends_at)
 * and stays visible for as long as the demo environment exists.
 */
class DemoAnnouncementSeeder extends Seeder
{
    /**
     * @var list<array{message: string, sort_order: int}>
     */
    private const ANNOUNCEMENTS = [
        ['message' => '🏏 Welcome to RajaBhoj Pawar Premier League', 'sort_order' => 0],
        ['message' => '📢 Check the latest match schedule and live scores', 'sort_order' => 1],
        ['message' => '🏆 Follow RPPL tournament updates here', 'sort_order' => 2],
    ];

    public function run(): void
    {
        $admin = User::where('email', 'admin@rppl.test')->first();

        if (! $admin) {
            return;
        }

        foreach (self::ANNOUNCEMENTS as $announcement) {
            Announcement::firstOrCreate(
                ['message' => $announcement['message']],
                [
                    'sort_order' => $announcement['sort_order'],
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
