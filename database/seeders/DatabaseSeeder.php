<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoEditionSeeder;
use Database\Seeders\Demo\DemoFinanceSeeder;
use Database\Seeders\Demo\DemoMatchSeeder;
use Database\Seeders\Demo\DemoNotificationSeeder;
use Database\Seeders\Demo\DemoPlayerSeeder;
use Database\Seeders\Demo\DemoRegistrationSeeder;
use Database\Seeders\Demo\DemoTeamSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the stakeholder demo dataset —
     * a complete, internally-consistent RPPL environment (users, players,
     * teams, editions, registrations/squads, matches through every
     * lifecycle state, finance/contributions) for `migrate:fresh --seed`.
     * Each Demo\* seeder owns one domain area and queries the records the
     * earlier seeders created rather than passing objects between them,
     * so this list is also the dependency order.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DemoUserSeeder::class,
            DemoPlayerSeeder::class,
            DemoTeamSeeder::class,
            DemoEditionSeeder::class,
            DemoRegistrationSeeder::class,
            DemoMatchSeeder::class,
            DemoFinanceSeeder::class,
            DemoNotificationSeeder::class,
        ]);
    }
}
