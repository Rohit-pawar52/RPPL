<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Team;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * The 4 RPPL 2026 teams and 2 venues for the local UAT reset — see
 * Rppl2026PlayerSeeder's docblock for why IPL-style franchise names are
 * used here specifically (an explicit, one-off local dataset choice).
 */
class Rppl2026TeamAndVenueSeeder extends Seeder
{
    /**
     * @var list<array{name: string, short_name: string}>
     */
    public const TEAMS = [
        ['name' => 'Mumbai Indians', 'short_name' => 'MI'],
        ['name' => 'Royal Challengers Bengaluru', 'short_name' => 'RCB'],
        ['name' => 'Chennai Super Kings', 'short_name' => 'CSK'],
        ['name' => 'Kolkata Knight Riders', 'short_name' => 'KKR'],
    ];

    /**
     * @var list<array{name: string, city: string, country: string}>
     */
    public const VENUES = [
        ['name' => 'RPPL Main Ground', 'city' => 'Dhar', 'country' => 'India'],
        ['name' => 'RPPL Cricket Ground', 'city' => 'Indore', 'country' => 'India'],
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
