<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Services\PlayerRegistration\PlayerRegistrationService;
use Illuminate\Database\Seeder;

/**
 * Four additional, fictional RPPL 2026 registrations that are NOT paid
 * and NOT squadded, so the admin "pending verification" banner and the
 * payment-status filters have real rows to show (the 60 core
 * registrations are all paid and squadded). Existing rows are never
 * touched. Created through PlayerRegistrationService so registration
 * numbers come from the real mechanism. No document files are attached;
 * OCR is marked failed like the other seeded registrations.
 */
class Rppl2026ExtraRegistrationSeeder extends Seeder
{
    /**
     * @var list<array{name: string, status: string, days_ago: int, role: string}>
     */
    private const PLAYERS = [
        ['name' => 'Omkar Jagtap', 'status' => 'pending', 'days_ago' => 2, 'role' => 'batter'],
        ['name' => 'Siddhesh Bhosale', 'status' => 'pending', 'days_ago' => 5, 'role' => 'bowler'],
        ['name' => 'Pratik Wagh', 'status' => 'failed', 'days_ago' => 9, 'role' => 'all_rounder'],
        ['name' => 'Yogesh Mhaske', 'status' => 'refunded', 'days_ago' => 16, 'role' => 'wicket_keeper'],
    ];

    public function __construct(private readonly PlayerRegistrationService $registrations) {}

    public function run(): void
    {
        $edition = Edition::where('year', Rppl2026EditionSeeder::YEAR)->firstOrFail();

        foreach (self::PLAYERS as $i => $data) {
            $player = Player::firstOrCreate(
                ['name' => $data['name']],
                [
                    'phone' => sprintf('9777%06d', 200000 + $i),
                    'email' => sprintf('extra.player%d@rppl2026-demo.test', $i + 1),
                    'date_of_birth' => now()->subYears(22 + $i * 3)->subDays(40 + $i * 11)->format('Y-m-d'),
                    'batting_style' => $i % 2 === 0 ? 'right_hand' : 'left_hand',
                    'bowling_style' => $data['role'] === 'batter' ? 'none' : 'right_arm',
                    'primary_role' => $data['role'],
                    'is_active' => true,
                ]
            );

            if (PlayerRegistration::where('edition_id', $edition->id)->where('player_id', $player->id)->exists()) {
                continue;
            }

            $registration = $this->registrations->createRegistration([
                'edition_id' => $edition->id,
                'player_id' => $player->id,
                'payment_status' => $data['status'],
                'registration_fee' => $edition->registration_fee ?? 500.00,
                'registered_at' => now()->subDays($data['days_ago']),
            ]);

            $registration->update(['ocr_status' => PlayerRegistration::OCR_FAILED]);
        }
    }
}
