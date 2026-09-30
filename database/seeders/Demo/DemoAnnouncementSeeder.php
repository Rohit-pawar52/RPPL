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

    /**
     * Historical announcements that never reach the live ticker: one
     * whose window has ended (computed status "expired") and one switched
     * off (computed status "disabled"). Absolute dates keep them in the
     * past forever.
     *
     * @var list<array{message: string, sort_order: int, is_active: bool, starts_at: string|null, ends_at: string|null}>
     */
    private const HISTORICAL = [
        ['message' => 'RPPL 2025 player registration is open until 15 March', 'sort_order' => 10, 'is_active' => true, 'starts_at' => '2025-02-01 04:30:00', 'ends_at' => '2025-03-15 14:30:00'],
        ['message' => 'Match day schedule for RPPL 2025 will be shared soon', 'sort_order' => 11, 'is_active' => false, 'starts_at' => null, 'ends_at' => null],
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

        foreach (self::HISTORICAL as $announcement) {
            Announcement::firstOrCreate(
                ['message' => $announcement['message']],
                [
                    'sort_order' => $announcement['sort_order'],
                    'is_active' => $announcement['is_active'],
                    'starts_at' => $announcement['starts_at'],
                    'ends_at' => $announcement['ends_at'],
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
