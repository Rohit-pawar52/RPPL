<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Team;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Database\Seeder;

/**
 * Registers all 60 Rppl2026PlayerSeeder players into RPPL 2026 and
 * squads each team with its own 15 (jersey 1-15, in roster order) — the
 * full squad, not just a Playing XI; XI selection for the 3 completed
 * matches happens later, per-match, in Rppl2026FixtureSeeder, exactly
 * as a real admin would do it close to match time.
 *
 * Registrations go through PlayerRegistrationService::createRegistration()
 * (never a raw PlayerRegistration::create()) so the real
 * assignRegistrationNumber() mechanism (RPPL-{year}-{id}) still applies —
 * registration numbers are never hand-crafted. No aadhaar/payment-proof
 * file is attached to any of these: this is fictional local dev data,
 * and those columns are nullable, so they are simply left null rather
 * than forced with a meaningless placeholder.
 */
class Rppl2026RegistrationAndSquadSeeder extends Seeder
{
    public function __construct(
        private readonly PlayerRegistrationService $registrations,
        private readonly TeamPlayerService $squads,
    ) {}

    public function run(): void
    {
        $edition = Edition::where('year', Rppl2026EditionSeeder::YEAR)->firstOrFail();

        foreach (Rppl2026PlayerSeeder::ROSTERS as $teamCode => $roster) {
            $team = Team::where('short_name', $teamCode)->firstOrFail();
            $editionTeam = EditionTeam::where('edition_id', $edition->id)->where('team_id', $team->id)->firstOrFail();

            foreach ($roster as $index => $playerData) {
                $player = Player::where('name', $playerData['name'])->firstOrFail();

                $registration = $this->register($edition, $player);

                if (! $registration->teamPlayer()->exists()) {
                    $this->squads->createTeamPlayer([
                        'edition_team_id' => $editionTeam->id,
                        'player_registration_id' => $registration->id,
                        'jersey_number' => $index + 1,
                        'role' => $playerData['role'],
                    ]);
                }
            }
        }
    }

    private function register(Edition $edition, Player $player): PlayerRegistration
    {
        $existing = PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->first();

        if ($existing) {
            return $existing;
        }

        $registration = $this->registrations->createRegistration([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => 'paid',
            'registration_fee' => $edition->registration_fee ?? 500.00,
            'registered_at' => now()->subWeeks(3),
        ]);

        // Every seeded registration was created without a payment proof
        // and without ever dispatching OCR — ocr_status must reflect
        // that conclusively, never sit at the migration's raw 'pending'
        // default (see Demo\DemoRegistrationSeeder's identical rationale).
        $registration->update(['ocr_status' => PlayerRegistration::OCR_FAILED]);

        return $registration;
    }
}
