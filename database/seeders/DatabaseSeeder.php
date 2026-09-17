<?php

namespace Database\Seeders;

use Database\Seeders\Development\RpplDemoSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RpplDemoSeeder::class,
        ]);
    }
}
