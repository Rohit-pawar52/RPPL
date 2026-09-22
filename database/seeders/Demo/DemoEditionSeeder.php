<?php

namespace Database\Seeders\Demo;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use Illuminate\Database\Seeder;

/**
 * Three editions telling the demo story required by the stakeholder demo
 * phase: one historical/completed season, one current/active season (the
 * richest dataset), and one upcoming/draft season. Only ONE edition may
 * ever have registration_open = true at a time (the public guest
 * registration gate) — that is RPPL 2025 here, the active edition.
 *
 * Also links each edition to its participating teams (EditionTeam is
 * plain reference data with no derived state, so — like the pre-existing
 * RpplDemoSeeder — this is a direct Eloquent write, not routed through a
 * service).
 */
class DemoEditionSeeder extends Seeder
{
    public const HISTORICAL_YEAR = 2024;

    public const ACTIVE_YEAR = 2025;

    public const UPCOMING_YEAR = 2026;

    public function run(): void
    {
        $historical = Edition::firstOrCreate(
            ['year' => self::HISTORICAL_YEAR],
            [
                'name' => 'RPPL 2024',
                'status' => 'completed',
                'registration_open' => false,
                'registration_fee' => 500.00,
            ]
        );

        $active = Edition::firstOrCreate(
            ['year' => self::ACTIVE_YEAR],
            [
                'name' => 'RPPL 2025',
                'status' => 'active',
                'registration_open' => true,
                'registration_fee' => 500.00,
            ]
        );

        $upcoming = Edition::firstOrCreate(
            ['year' => self::UPCOMING_YEAR],
            [
                'name' => 'RPPL 2026',
                'status' => 'upcoming',
                'registration_open' => false,
                'registration_fee' => null,
            ]
        );

        $teams = Team::orderBy('id')->get();

        // Historical: 4 of the 6 teams participated.
        foreach ($teams->slice(0, 4) as $team) {
            EditionTeam::firstOrCreate(['edition_id' => $historical->id, 'team_id' => $team->id]);
        }

        // Active: all 6 teams participate — the richest edition.
        foreach ($teams as $team) {
            EditionTeam::firstOrCreate(['edition_id' => $active->id, 'team_id' => $team->id]);
        }

        // Upcoming: only 2 teams have committed so far — still a draft.
        foreach ($teams->slice(0, 2) as $team) {
            EditionTeam::firstOrCreate(['edition_id' => $upcoming->id, 'team_id' => $team->id]);
        }
    }
}
