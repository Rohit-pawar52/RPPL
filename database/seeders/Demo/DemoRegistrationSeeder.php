<?php

namespace Database\Seeders\Demo;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Database\Seeder;

/**
 * Registers the DemoPlayerSeeder player pool into the historical and
 * active editions, and builds each participating team's squad for those
 * two editions. The upcoming (draft) edition deliberately gets no
 * registrations yet — realistic for a season that hasn't opened.
 *
 * Registrations are created via PlayerRegistrationService::
 * createRegistration() — the SAME lower-level service the public guest
 * flow's GuestPlayerRegistrationService wraps — never
 * GuestPlayerRegistrationService::register() itself, because that method
 * dispatches ProcessPaymentProofOcr after commit, and this phase
 * explicitly forbids seeders from ever dispatching an OCR job. Using
 * createRegistration() directly still exercises the real
 * assignRegistrationNumber() mechanism (RPPL-{year}-{id}), so
 * registration numbers are never hand-crafted.
 *
 * No seeded registration has a payment_proof_path (no fake screenshots
 * are generated), so — consistent with what ProcessPaymentProofOcr::
 * handle() itself would set for a registration with no payment proof —
 * ocr_status is explicitly set to OCR_FAILED right after creation,
 * rather than left at the migration's 'pending' default, which would
 * misleadingly suggest a job is still queued to process it.
 */
class DemoRegistrationSeeder extends Seeder
{
    public function __construct(
        private readonly PlayerRegistrationService $registrations,
        private readonly TeamPlayerService $squads,
    ) {}

    public function run(): void
    {
        $players = Player::orderBy('id')->get()->values();

        $historical = Edition::where('year', DemoEditionSeeder::HISTORICAL_YEAR)->firstOrFail();
        $active = Edition::where('year', DemoEditionSeeder::ACTIVE_YEAR)->firstOrFail();

        $historicalTeams = EditionTeam::where('edition_id', $historical->id)->orderBy('id')->get();
        $activeTeams = EditionTeam::where('edition_id', $active->id)->orderBy('id')->get();

        $this->seedHistoricalEdition($historical, $historicalTeams, $players);
        $this->seedActiveEdition($active, $activeTeams, $players);
    }

    /**
     * 4 teams x 6 players = squads for players[0..23], all paid except
     * one refunded (realistic wrap-up variety for a completed season).
     */
    private function seedHistoricalEdition(Edition $edition, $editionTeams, $players): void
    {
        $playerIndex = 0;

        foreach ($editionTeams as $editionTeam) {
            for ($jersey = 1; $jersey <= 6; $jersey++) {
                $player = $players[$playerIndex];
                $isLast = $playerIndex === 23;

                $registration = $this->register($edition, $player, $isLast ? 'refunded' : 'paid', monthsAgo: 14);

                $this->squads->createTeamPlayer([
                    'edition_team_id' => $editionTeam->id,
                    'player_registration_id' => $registration->id,
                    'jersey_number' => $jersey,
                    'role' => $player->primary_role,
                ]);

                $playerIndex++;
            }
        }
    }

    /**
     * 6 teams x 7 players = squads for players[0..41] (paid), plus 3 more
     * registrations (players[42..44]) left unsquadded to demonstrate
     * pending/failed/refunded payment variety on the currently-open
     * edition.
     */
    private function seedActiveEdition(Edition $edition, $editionTeams, $players): void
    {
        $playerIndex = 0;

        foreach ($editionTeams as $editionTeam) {
            for ($jersey = 1; $jersey <= 7; $jersey++) {
                $player = $players[$playerIndex];

                $registration = $this->register($edition, $player, 'paid', monthsAgo: 2);

                $this->squads->createTeamPlayer([
                    'edition_team_id' => $editionTeam->id,
                    'player_registration_id' => $registration->id,
                    'jersey_number' => $jersey,
                    'role' => $player->primary_role,
                ]);

                $playerIndex++;
            }
        }

        $this->register($edition, $players[42], 'pending', monthsAgo: 1);
        $this->register($edition, $players[43], 'failed', monthsAgo: 1);
        $this->register($edition, $players[44], 'refunded', monthsAgo: 1);
    }

    private function register(Edition $edition, Player $player, string $paymentStatus, int $monthsAgo): PlayerRegistration
    {
        $existing = PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->first();

        if ($existing) {
            return $existing;
        }

        $registration = $this->registrations->createRegistration([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => $paymentStatus,
            'registration_fee' => $edition->registration_fee ?? 500.00,
            'registered_at' => now()->subMonths($monthsAgo),
        ]);

        $registration->update(['ocr_status' => PlayerRegistration::OCR_FAILED]);

        return $registration;
    }
}
