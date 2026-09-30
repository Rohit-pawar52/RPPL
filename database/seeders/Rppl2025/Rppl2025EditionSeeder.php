<?php

namespace Database\Seeders\Rppl2025;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use Database\Seeders\Rppl2026\Rppl2026TeamAndVenueSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * The historical RPPL 2025 edition: a COMPLETED previous season with the
 * same 4 master teams, each with its OWN edition_teams row (the 2025
 * participation is never a pointer at the 2026 rows, so the season stays
 * independently queryable). Runs after Rppl2026TeamAndVenueSeeder, which
 * owns the master teams and venues — this seeder only reads them.
 *
 * Dates are absolute 2025 dates (a finished season must never drift with
 * "now"): registration Feb-Mar 2025, matches in April 2025 (see
 * Rppl2025FixtureSeeder). registration_open is false and the reminder is
 * off, matching a season that is over.
 */
class Rppl2025EditionSeeder extends Seeder
{
    public const YEAR = 2025;

    public const REGISTRATION_FEE = 400.00;

    public function run(): void
    {
        $edition = Edition::firstOrCreate(
            ['year' => self::YEAR],
            [
                'name' => 'RPPL 2025',
                'status' => 'completed',
                'registration_open' => false,
                'registration_fee' => self::REGISTRATION_FEE,
                'registration_opens_at' => Carbon::create(2025, 2, 1, 10, 0, 0, 'Asia/Kolkata')->utc(),
                'registration_closes_at' => Carbon::create(2025, 3, 15, 20, 0, 0, 'Asia/Kolkata')->utc(),
            ]
        );

        foreach (Rppl2026TeamAndVenueSeeder::TEAMS as $definition) {
            $team = Team::where('short_name', $definition['short_name'])->firstOrFail();

            EditionTeam::firstOrCreate(['edition_id' => $edition->id, 'team_id' => $team->id]);
        }
    }
}
