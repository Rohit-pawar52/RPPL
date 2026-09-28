<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAnnouncementSeeder;
use Database\Seeders\Demo\DemoContentPageSeeder;
use Database\Seeders\Demo\DemoNotificationSeeder;
use Database\Seeders\Demo\DemoRuleSeeder;
use Database\Seeders\Demo\DemoSettingSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Database\Seeders\Rppl2026\Rppl2026EditionSeeder;
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
     * Seed the application's database with the RPPL 2026 local UAT
     * dataset — a single active edition (Mumbai Indians, Royal
     * Challengers Bengaluru, Chennai Super Kings, Kolkata Knight
     * Riders), 60 players/60 registrations, full 15-player squads, and 6
     * league fixtures (3 played to a real ball-by-ball completed result
     * through the actual scoring engine, 3 left scheduled) — for
     * `migrate:fresh --seed`.
     *
     * The generic (non-edition-specific) Demo\* seeders — roles, system
     * settings, content pages, the admin/scorer login accounts,
     * notification masters, and public announcements — are unchanged
     * and still run first. Rppl2026\* seeders replace the previous
     * Demo\DemoPlayerSeeder/DemoTeamSeeder/DemoEditionSeeder/
     * DemoRegistrationSeeder/DemoMatchSeeder/DemoFinanceSeeder chain,
     * which built a 3-edition, 6-generic-team dataset — this is a
     * deliberate, explicitly-requested replacement of that dataset for
     * local manual UAT (see the RPPL 2026 reset completion report), not
     * an addition alongside it. The old Demo\* classes for that
     * replaced chain are left in place, unused, in case that dataset
     * shape is wanted again later.
     *
     * Each seeder owns one domain area and queries the records the
     * earlier seeders created rather than passing objects between them,
     * so this list is also the dependency order.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DemoSettingSeeder::class,
            DemoContentPageSeeder::class,
            DemoUserSeeder::class,
            Rppl2026PlayerSeeder::class,
            Rppl2026TeamAndVenueSeeder::class,
            Rppl2026EditionSeeder::class,
            Rppl2026RegistrationAndSquadSeeder::class,
            Rppl2026FixtureSeeder::class,
            Rppl2026FinanceSeeder::class,
            DemoNotificationSeeder::class,
            DemoAnnouncementSeeder::class,
            DemoRuleSeeder::class,
        ]);
    }
}
