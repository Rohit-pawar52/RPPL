<?php

namespace Database\Seeders\Demo;

use App\Models\Player;
use Illuminate\Database\Seeder;

/**
 * ~45 reusable Player master records for the stakeholder demo dataset.
 * Names are believable Indian cricket-player-style names, not real
 * public figures and not the previous RpplDemoSeeder's real-IPL-player
 * names. Phone/email are synthetic (never real), derived deterministically
 * from each player's position in the list so re-reading this file always
 * explains exactly why a given value looks the way it does.
 *
 * Reused across editions (DemoRegistrationSeeder registers the same pool
 * into more than one edition) — a real player naturally plays multiple
 * seasons, and player_registrations.edition_id+player_id is unique per
 * edition, not globally, so this is safe.
 */
class DemoPlayerSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const NAMES = [
        'Rahul Sharma', 'Aditya Verma', 'Vikram Patil', 'Suresh Deshmukh', 'Ramesh Kulkarni',
        'Ajay Joshi', 'Sanjay Pawar', 'Vijay Shinde', 'Manoj Gaikwad', 'Deepak Jadhav',
        'Rajesh More', 'Anil Bhosale', 'Sunil Chavan', 'Prakash Yadav', 'Nitin Singh',
        'Amit Kumar', 'Vikas Mishra', 'Rohan Pandey', 'Karan Tiwari', 'Arjun Rao',
        'Aryan Reddy', 'Yash Nair', 'Kunal Menon', 'Varun Iyer', 'Siddharth Gupta',
        'Abhishek Agarwal', 'Gaurav Khan', 'Saurabh Shaikh', 'Pankaj Ansari', 'Naveen Kadam',
        'Harish Sharma', 'Mahesh Verma', 'Dinesh Patil', 'Ganesh Deshmukh', 'Ravindra Kulkarni',
        'Jitendra Joshi', 'Devendra Pawar', 'Mukesh Shinde', 'Ashok Gaikwad', 'Sandeep Jadhav',
        'Pradeep More', 'Kuldeep Bhosale', 'Harshad Chavan', 'Nikhil Yadav', 'Tarun Singh',
    ];

    /**
     * Cycled by index so roles land at a realistic squad-composition
     * ratio (mostly batters/bowlers, fewer all-rounders, fewest
     * wicket-keepers) rather than an even 1/4-1/4-1/4-1/4 split.
     *
     * @var list<string>
     */
    private const ROLE_CYCLE = [
        'batter', 'bowler', 'batter', 'all_rounder', 'bowler',
        'batter', 'wicket_keeper', 'bowler', 'batter', 'all_rounder',
    ];

    /**
     * Two players deliberately seeded inactive — a very small minority,
     * per the phase's "mostly active" requirement, exercising the
     * is_active filter in player listings/selection dropdowns.
     *
     * @var list<int>
     */
    private const INACTIVE_INDEXES = [39, 44];

    public function run(): void
    {
        foreach (self::NAMES as $index => $name) {
            $role = self::ROLE_CYCLE[$index % count(self::ROLE_CYCLE)];

            Player::firstOrCreate(
                ['name' => $name, 'phone' => $this->phoneFor($index)],
                [
                    'is_active' => ! in_array($index, self::INACTIVE_INDEXES, true),
                    'email' => $this->emailFor($name, $index),
                    'date_of_birth' => now()->subYears(19 + ($index % 20))->subDays($index * 5)->format('Y-m-d'),
                    'batting_style' => $index % 5 === 0 ? 'left_hand' : 'right_hand',
                    'bowling_style' => $role === 'batter' ? 'none' : ($index % 2 === 0 ? 'right_arm' : 'left_arm'),
                    'primary_role' => $role,
                ]
            );
        }
    }

    private function phoneFor(int $index): string
    {
        return sprintf('9%09d', 100000000 + $index);
    }

    private function emailFor(string $name, int $index): string
    {
        return strtolower(str_replace(' ', '.', $name)).$index.'@rppl-demo.test';
    }
}
