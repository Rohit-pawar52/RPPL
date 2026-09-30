<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAnnouncementSeeder;
use Database\Seeders\Demo\DemoContentPageSeeder;
use Database\Seeders\Demo\DemoNewsSeeder;
use Database\Seeders\Demo\DemoNotificationSeeder;
use Database\Seeders\Demo\DemoPhotoSeeder;
use Database\Seeders\Demo\DemoRuleSeeder;
use Database\Seeders\Demo\DemoSettingSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Database\Seeders\Rppl2025\Rppl2025EditionSeeder;
use Database\Seeders\Rppl2025\Rppl2025FinanceSeeder;
use Database\Seeders\Rppl2025\Rppl2025FixtureSeeder;
use Database\Seeders\Rppl2025\Rppl2025RegistrationAndSquadSeeder;
use Database\Seeders\Rppl2026\Rppl2026EditionSeeder;
use Database\Seeders\Rppl2026\Rppl2026ExtraRegistrationSeeder;
use Database\Seeders\Rppl2026\Rppl2026FinanceSeeder;
use Database\Seeders\Rppl2026\Rppl2026FixtureSeeder;
use Database\Seeders\Rppl2026\Rppl2026PlayerSeeder;
use Database\Seeders\Rppl2026\Rppl2026RegistrationAndSquadSeeder;
use Database\Seeders\Rppl2026\Rppl2026TeamAndVenueSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds the local demo/UAT dataset for `migrate:fresh --seed`: two
     * independent seasons of the same 4 teams (Mumbai Indians, Royal
     * Challengers Bengaluru, Chennai Super Kings, Kolkata Knight Riders).
     *
     *  - RPPL 2026 (active): 60 players, full 15-player squads, 6 league
     *    fixtures (3 played, 3 scheduled), plus a few extra registrations
     *    in pending/failed/refunded states.
     *  - RPPL 2025 (completed): its own edition-team rows, 13-player
     *    squads and 7 five-over matches (6 league + a Final).
     * Every match is played ball-by-ball through the real scoring services,
     * so scorecards, standings and statistics are all derived, never
     * hand-written. News, photos and finance for both seasons sit on top.
     *
     * Seeders are idempotent (firstOrCreate / early return on existing
     * data), so re-running this against a seeded database adds nothing.
     * Each seeder owns one domain area and queries what earlier seeders
     * created, so this list is also the dependency order. The older
     * Demo\* player/team/edition/match/finance seeders are left in place,
     * unused, in case that dataset shape is wanted again.
     */
    public function run(): void
    {
        $this->call([
            // Reference data, accounts, settings and fixed public pages
            RoleSeeder::class,
            DemoSettingSeeder::class,
            DemoContentPageSeeder::class,
            DemoUserSeeder::class,

            // Master data (teams, venues, players) shared by every edition
            Rppl2026PlayerSeeder::class,
            Rppl2026TeamAndVenueSeeder::class,

            // RPPL 2026: the current, active season
            Rppl2026EditionSeeder::class,
            Rppl2026RegistrationAndSquadSeeder::class,
            Rppl2026ExtraRegistrationSeeder::class,
            Rppl2026FixtureSeeder::class,
            Rppl2026FinanceSeeder::class,

            // RPPL 2025: the completed previous season (4 teams, 7 five-over matches)
            Rppl2025EditionSeeder::class,
            Rppl2025RegistrationAndSquadSeeder::class,
            Rppl2025FixtureSeeder::class,
            Rppl2025FinanceSeeder::class,

            // Public content and communication. News reads the match results
            // above, so it must run after both seasons' fixtures.
            DemoNotificationSeeder::class,
            DemoAnnouncementSeeder::class,
            DemoRuleSeeder::class,
            DemoNewsSeeder::class,
            DemoPhotoSeeder::class,
        ]);
    }
}
