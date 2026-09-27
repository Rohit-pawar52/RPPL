<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Player;
use Illuminate\Database\Seeder;

/**
 * 60 reusable Player master records (4 teams x 15) for the RPPL 2026
 * local UAT reset — recognizable IPL-style names so manual testing feels
 * natural, but entirely fictional development data: this is not a claim
 * about any real player's actual 2026 squad, and every contact field
 * (phone/email/DOB) is synthetic, derived deterministically from the
 * player's position in the list, never a real person's actual details.
 *
 * A prior phase (see Demo\DemoTeamSeeder's docblock) deliberately moved
 * the STANDING demo dataset away from real IPL franchise names to avoid
 * any trademark-adjacent naming in committed source. This seeder
 * reintroduces IPL-style names ONLY because the user explicitly asked
 * for them for this specific local manual-UAT reset, fully aware these
 * are fictional dev names — see the completion report for that
 * decision's context. It is a separate, explicitly-invoked dataset, not
 * a change to that prior policy.
 */
class Rppl2026PlayerSeeder extends Seeder
{
    /**
     * @var array<string, list<array{name: string, role: string}>>
     */
    public const ROSTERS = [
        'MI' => [
            ['name' => 'Rohit Sharma', 'role' => 'batter'],
            ['name' => 'Suryakumar Yadav', 'role' => 'batter'],
            ['name' => 'Tilak Varma', 'role' => 'batter'],
            ['name' => 'Hardik Pandya', 'role' => 'all_rounder'],
            ['name' => 'Jasprit Bumrah', 'role' => 'bowler'],
            ['name' => 'Trent Boult', 'role' => 'bowler'],
            ['name' => 'Deepak Chahar', 'role' => 'bowler'],
            ['name' => 'Naman Dhir', 'role' => 'all_rounder'],
            ['name' => 'Will Jacks', 'role' => 'all_rounder'],
            ['name' => 'Ryan Rickelton', 'role' => 'wicket_keeper'],
            ['name' => 'Mitchell Santner', 'role' => 'all_rounder'],
            ['name' => 'Robin Minz', 'role' => 'wicket_keeper'],
            ['name' => 'Karn Sharma', 'role' => 'bowler'],
            ['name' => 'Ashwani Kumar', 'role' => 'bowler'],
            ['name' => 'Raj Angad Bawa', 'role' => 'all_rounder'],
        ],
        'RCB' => [
            ['name' => 'Virat Kohli', 'role' => 'batter'],
            ['name' => 'Rajat Patidar', 'role' => 'batter'],
            ['name' => 'Phil Salt', 'role' => 'wicket_keeper'],
            ['name' => 'Jitesh Sharma', 'role' => 'wicket_keeper'],
            ['name' => 'Liam Livingstone', 'role' => 'all_rounder'],
            ['name' => 'Krunal Pandya', 'role' => 'all_rounder'],
            ['name' => 'Tim David', 'role' => 'batter'],
            ['name' => 'Bhuvneshwar Kumar', 'role' => 'bowler'],
            ['name' => 'Josh Hazlewood', 'role' => 'bowler'],
            ['name' => 'Yash Dayal', 'role' => 'bowler'],
            ['name' => 'Suyash Sharma', 'role' => 'bowler'],
            ['name' => 'Devdutt Padikkal', 'role' => 'batter'],
            ['name' => 'Romario Shepherd', 'role' => 'all_rounder'],
            ['name' => 'Swapnil Singh', 'role' => 'all_rounder'],
            ['name' => 'Rasikh Salam', 'role' => 'bowler'],
        ],
        'CSK' => [
            ['name' => 'Ruturaj Gaikwad', 'role' => 'batter'],
            ['name' => 'MS Dhoni', 'role' => 'wicket_keeper'],
            ['name' => 'Ravindra Jadeja', 'role' => 'all_rounder'],
            ['name' => 'Shivam Dube', 'role' => 'all_rounder'],
            ['name' => 'Devon Conway', 'role' => 'batter'],
            ['name' => 'Rachin Ravindra', 'role' => 'all_rounder'],
            ['name' => 'Rahul Tripathi', 'role' => 'batter'],
            ['name' => 'Ravichandran Ashwin', 'role' => 'bowler'],
            ['name' => 'Noor Ahmad', 'role' => 'bowler'],
            ['name' => 'Matheesha Pathirana', 'role' => 'bowler'],
            ['name' => 'Khaleel Ahmed', 'role' => 'bowler'],
            ['name' => 'Sam Curran', 'role' => 'all_rounder'],
            ['name' => 'Deepak Hooda', 'role' => 'all_rounder'],
            ['name' => 'Vijay Shankar', 'role' => 'all_rounder'],
            ['name' => 'Mukesh Choudhary', 'role' => 'bowler'],
        ],
        'KKR' => [
            ['name' => 'Ajinkya Rahane', 'role' => 'batter'],
            ['name' => 'Rinku Singh', 'role' => 'batter'],
            ['name' => 'Venkatesh Iyer', 'role' => 'all_rounder'],
            ['name' => 'Andre Russell', 'role' => 'all_rounder'],
            ['name' => 'Sunil Narine', 'role' => 'all_rounder'],
            ['name' => 'Quinton de Kock', 'role' => 'wicket_keeper'],
            ['name' => 'Rahmanullah Gurbaz', 'role' => 'wicket_keeper'],
            ['name' => 'Varun Chakravarthy', 'role' => 'bowler'],
            ['name' => 'Harshit Rana', 'role' => 'bowler'],
            ['name' => 'Anrich Nortje', 'role' => 'bowler'],
            ['name' => 'Vaibhav Arora', 'role' => 'bowler'],
            ['name' => 'Ramandeep Singh', 'role' => 'all_rounder'],
            ['name' => 'Moeen Ali', 'role' => 'all_rounder'],
            ['name' => 'Manish Pandey', 'role' => 'batter'],
            ['name' => 'Angkrish Raghuvanshi', 'role' => 'batter'],
        ],
    ];

    public function run(): void
    {
        $index = 0;

        foreach (self::ROSTERS as $teamCode => $roster) {
            foreach ($roster as $player) {
                Player::firstOrCreate(
                    ['name' => $player['name']],
                    [
                        'phone' => $this->phoneFor($index),
                        'email' => $this->emailFor($player['name'], $index),
                        'date_of_birth' => now()->subYears(20 + ($index % 18))->subDays($index * 7)->format('Y-m-d'),
                        'batting_style' => $index % 4 === 0 ? 'left_hand' : 'right_hand',
                        'bowling_style' => $player['role'] === 'batter' ? 'none' : ($index % 2 === 0 ? 'right_arm' : 'left_arm'),
                        'primary_role' => $player['role'],
                        'is_active' => true,
                    ]
                );

                $index++;
            }
        }
    }

    /**
     * Synthetic, obviously-fictional phone number — never a real
     * person's number. The 555 prefix mirrors the well-known "reserved
     * for fiction" convention.
     */
    private function phoneFor(int $index): string
    {
        return sprintf('9555%06d', 100000 + $index);
    }

    private function emailFor(string $name, int $index): string
    {
        return strtolower(str_replace([' ', '.'], ['.', ''], $name)).$index.'@rppl2026-demo.test';
    }
}
