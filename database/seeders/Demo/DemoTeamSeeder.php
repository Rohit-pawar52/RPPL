<?php

namespace Database\Seeders\Demo;

use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * ~6 reusable Team master records and ~3 Venues for the stakeholder demo
 * dataset. Names are generic local-cricket-club style names — deliberately
 * NOT real IPL franchise names (the previous RpplDemoSeeder used "Mumbai
 * Indians", "Chennai Super Kings", etc., which this dataset replaces).
 */
class DemoTeamSeeder extends Seeder
{
    /**
     * @var list<array{name: string, short_name: string}>
     */
    public const TEAMS = [
        ['name' => 'Bhoj Warriors', 'short_name' => 'BHW'],
        ['name' => 'Sahyadri Strikers', 'short_name' => 'SAH'],
        ['name' => 'Deccan Gladiators', 'short_name' => 'DEC'],
        ['name' => 'Konkan Tigers', 'short_name' => 'KOT'],
        ['name' => 'Malwa Chargers', 'short_name' => 'MWC'],
        ['name' => 'Vidarbha Titans', 'short_name' => 'VIT'],
    ];

    /**
     * @var list<array{name: string, city: string, country: string}>
     */
    public const VENUES = [
        ['name' => 'Bhoj Sports Complex', 'city' => 'Dhar', 'country' => 'India'],
        ['name' => 'Community Cricket Ground', 'city' => 'Indore', 'country' => 'India'],
        ['name' => 'District Stadium', 'city' => 'Ujjain', 'country' => 'India'],
    ];

    public function run(): void
    {
        foreach (self::TEAMS as $team) {
            Team::firstOrCreate(
                ['name' => $team['name']],
                ['short_name' => $team['short_name'], 'is_active' => true]
            );
        }

        foreach (self::VENUES as $venue) {
            Venue::firstOrCreate(
                ['name' => $venue['name']],
                ['city' => $venue['city'], 'country' => $venue['country'], 'is_active' => true]
            );
        }
    }
}
