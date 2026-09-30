<?php

namespace Database\Seeders\Rppl2025;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Team;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use App\Services\TeamPlayer\TeamPlayerService;
use Database\Seeders\Rppl2026\Rppl2026PlayerSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * RPPL 2025 squads: each team fields the first 13 players of its 2026
 * roster (same jersey number = roster position + 1), so the last two of
 * every roster are "new for 2026". Three of those newcomers also have a
 * 2025 registration that never made a squad (two refunded, one failed).
 *
 * Registrations go through PlayerRegistrationService::createRegistration()
 * so registration numbers come from the real assignRegistrationNumber()
 * mechanism. registered_at values are fixed 2025 timestamps (never now()),
 * spread across the Feb-Mar 2025 registration window. Safe to re-run.
 */
class Rppl2025RegistrationAndSquadSeeder extends Seeder
{
    private const SQUAD_SIZE = 13;

    /**
     * 2026-only newcomers who still registered in 2025 without being squadded.
     *
     * @var array<string, array{team: string, index: int, status: string}>
     */
    private const UNSQUADDED = [
        'Ashwani Kumar' => ['team' => 'MI', 'index' => 13, 'status' => 'refunded'],
        'Swapnil Singh' => ['team' => 'RCB', 'index' => 13, 'status' => 'failed'],
        'Mukesh Choudhary' => ['team' => 'CSK', 'index' => 14, 'status' => 'refunded'],
    ];

    public function __construct(
        private readonly PlayerRegistrationService $registrations,
        private readonly TeamPlayerService $squads,
    ) {}

    public function run(): void
    {
        $edition = Edition::where('year', Rppl2025EditionSeeder::YEAR)->firstOrFail();
        $editionTeams = [];

        foreach (array_keys(Rppl2026PlayerSeeder::ROSTERS) as $code) {
            $team = Team::where('short_name', $code)->firstOrFail();
            $editionTeams[$code] = EditionTeam::where('edition_id', $edition->id)->where('team_id', $team->id)->firstOrFail();
        }

        $sequence = 0;

        // Round-robin across teams so registration numbers interleave naturally.
        for ($index = 0; $index < self::SQUAD_SIZE; $index++) {
            foreach (Rppl2026PlayerSeeder::ROSTERS as $code => $roster) {
                $playerData = $roster[$index];
                $player = Player::where('name', $playerData['name'])->firstOrFail();
                $registration = $this->register($edition, $player, 'paid', $sequence++);

                if (! $registration->teamPlayer()->exists()) {
                    $this->squads->createTeamPlayer([
                        'edition_team_id' => $editionTeams[$code]->id,
                        'player_registration_id' => $registration->id,
                        'jersey_number' => $index + 1,
                        'role' => $playerData['role'],
                    ]);
                }
            }
        }

        foreach (self::UNSQUADDED as $name => $definition) {
            $player = Player::where('name', $name)->firstOrFail();
            $this->register($edition, $player, $definition['status'], $sequence++);
        }
    }

    private function register(Edition $edition, Player $player, string $paymentStatus, int $sequence): PlayerRegistration
    {
        $existing = PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->first();

        if ($existing) {
            return $existing;
        }

        // 16h steps from 3 Feb 2025 10:00 IST: 55 registrations end on 11 Mar.
        $registeredAt = Carbon::create(2025, 2, 3, 10, 0, 0, 'Asia/Kolkata')
            ->addHours($sequence * 16)
            ->addMinutes(($sequence * 7) % 60)
            ->utc();

        $registration = $this->registrations->createRegistration([
            'edition_id' => $edition->id,
            'player_id' => $player->id,
            'payment_status' => $paymentStatus,
            'registration_fee' => $edition->registration_fee ?? Rppl2025EditionSeeder::REGISTRATION_FEE,
            'registered_at' => $registeredAt,
        ]);

        // No payment proof was ever attached or OCR-dispatched (see the 2026 seeder).
        $registration->update(['ocr_status' => PlayerRegistration::OCR_FAILED]);

        return $registration;
    }
}
