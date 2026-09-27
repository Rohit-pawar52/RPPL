<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * A single active RPPL 2026 edition with all 4 RPPL 2026 teams
 * participating — this local UAT reset deliberately keeps only one
 * edition (no historical/upcoming seasons), per the task's explicit
 * scope. registration_open is false: the squads/registrations below are
 * already fully seeded and the tournament is already mid-league, so
 * "still accepting new guest registrations" would not tell a consistent
 * story.
 */
class Rppl2026EditionSeeder extends Seeder
{
    public const YEAR = 2026;

    public function run(): void
    {
        $edition = Edition::firstOrCreate(
            ['year' => self::YEAR],
            [
                'name' => 'RPPL 2026',
                'status' => 'active',
                'registration_open' => false,
                'registration_fee' => 500.00,
            ]
        );

        foreach (Team::orderBy('id')->get() as $team) {
            EditionTeam::firstOrCreate(['edition_id' => $edition->id, 'team_id' => $team->id]);
        }
    }
}
