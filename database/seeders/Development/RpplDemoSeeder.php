<?php

namespace Database\Seeders\Development;

use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a single, coherent, recognizable DEVELOPMENT dataset for the
 * RPPL tournament scoring schema, up to (but not including) any
 * ball-by-ball delivery data.
 *
 * IMPORTANT: This is development/demo data only. It does not represent
 * actual RPPL tournament records, and the player/team names used are
 * for developer recognizability only.
 */
class RpplDemoSeeder extends Seeder
{
    /**
     * Demo squads, keyed by team name. Player personal metadata
     * (date_of_birth, batting_style, bowling_style, primary_role) is
     * deliberately left null for every seeded player rather than
     * guessed, per the nullable-metadata guidance for this phase.
     */
    private const TEAMS = [
        'Mumbai Indians' => [
            'short_name' => 'MI',
            'players' => ['Rohit Sharma', 'Suryakumar Yadav', 'Hardik Pandya', 'Jasprit Bumrah', 'Tilak Varma'],
        ],
        'Chennai Super Kings' => [
            'short_name' => 'CSK',
            'players' => ['MS Dhoni', 'Ruturaj Gaikwad', 'Ravindra Jadeja', 'Shivam Dube', 'Matheesha Pathirana'],
        ],
        'Royal Challengers Bengaluru' => [
            'short_name' => 'RCB',
            'players' => ['Virat Kohli', 'Rajat Patidar', 'Jitesh Sharma', 'Yash Dayal', 'Krunal Pandya'],
        ],
        'Kolkata Knight Riders' => [
            'short_name' => 'KKR',
            'players' => ['Rinku Singh', 'Sunil Narine', 'Andre Russell', 'Varun Chakravarthy', 'Harshit Rana'],
        ],
    ];

    public function run(): void
    {
        $edition = $this->seedEdition();
        $editionTeams = $this->seedTeamsAndEditionTeams($edition);
        $teamPlayers = $this->seedPlayersAndSquads($edition, $editionTeams);
        $venues = $this->seedVenues();
        $matches = $this->seedMatches($edition, $editionTeams, $venues);

        // Only the first fixture gets a demo playing squad + innings shell,
        // per the phase instructions (relationship verification, not a
        // fully populated tournament).
        $this->seedMatchPlayersAndInnings($matches[1], $editionTeams, $teamPlayers);

        $this->seedDevelopmentUsers();
    }

    private function seedEdition(): Edition
    {
        return Edition::firstOrCreate(
            ['year' => 2026],
            ['name' => 'RPPL Demo 2026', 'status' => 'upcoming']
        );
    }

    /**
     * @return array<string, EditionTeam> keyed by team name
     */
    private function seedTeamsAndEditionTeams(Edition $edition): array
    {
        $editionTeams = [];

        foreach (self::TEAMS as $teamName => $config) {
            $team = Team::firstOrCreate(
                ['name' => $teamName],
                ['short_name' => $config['short_name']]
            );

            $editionTeams[$teamName] = EditionTeam::firstOrCreate([
                'edition_id' => $edition->id,
                'team_id' => $team->id,
            ]);
        }

        return $editionTeams;
    }

    /**
     * @param  array<string, EditionTeam>  $editionTeams
     * @return array<string, Collection<int, TeamPlayer>> keyed by team name
     */
    private function seedPlayersAndSquads(Edition $edition, array $editionTeams): array
    {
        $teamPlayers = [];

        foreach (self::TEAMS as $teamName => $config) {
            $editionTeam = $editionTeams[$teamName];
            $squad = [];
            $jerseyNumber = 1;

            foreach ($config['players'] as $playerName) {
                $player = Player::firstOrCreate(['name' => $playerName]);

                $registration = PlayerRegistration::firstOrCreate(
                    ['edition_id' => $edition->id, 'player_id' => $player->id],
                    [
                        'payment_status' => 'paid',
                        'registration_fee' => 5000.00,
                        'registered_at' => now(),
                    ]
                );

                // Sequential jersey numbers per team keep UNIQUE(edition_team_id,
                // jersey_number) trivially satisfied without inventing real
                // player jersey numbers.
                $squad[] = TeamPlayer::firstOrCreate(
                    ['player_registration_id' => $registration->id],
                    ['edition_team_id' => $editionTeam->id, 'jersey_number' => $jerseyNumber]
                );

                $jerseyNumber++;
            }

            $teamPlayers[$teamName] = collect($squad);
        }

        return $teamPlayers;
    }

    /**
     * @return list<Venue>
     */
    private function seedVenues(): array
    {
        return [
            Venue::firstOrCreate(
                ['name' => 'Wankhede Stadium'],
                ['city' => 'Mumbai', 'country' => 'India']
            ),
            Venue::firstOrCreate(
                ['name' => 'M. A. Chidambaram Stadium'],
                ['city' => 'Chennai', 'country' => 'India']
            ),
        ];
    }

    /**
     * @param  array<string, EditionTeam>  $editionTeams
     * @param  list<Venue>  $venues
     * @return array<int, GameMatch> keyed by match_number
     */
    private function seedMatches(Edition $edition, array $editionTeams, array $venues): array
    {
        $fixtures = [
            1 => ['Mumbai Indians', 'Chennai Super Kings', $venues[0]],
            2 => ['Royal Challengers Bengaluru', 'Kolkata Knight Riders', $venues[1]],
            3 => ['Mumbai Indians', 'Royal Challengers Bengaluru', $venues[0]],
        ];

        $matches = [];
        $scheduledAt = now()->addDays(7);

        foreach ($fixtures as $matchNumber => [$teamAName, $teamBName, $venue]) {
            $matches[$matchNumber] = GameMatch::firstOrCreate(
                ['edition_id' => $edition->id, 'match_number' => $matchNumber],
                [
                    'edition_team_a_id' => $editionTeams[$teamAName]->id,
                    'edition_team_b_id' => $editionTeams[$teamBName]->id,
                    'venue_id' => $venue->id,
                    'match_stage' => 'league',
                    'overs_per_innings' => 20,
                    'scheduled_at' => $scheduledAt,
                    'match_status' => 'scheduled',
                    // toss_winner_team_id, toss_decision, winner_team_id,
                    // result_type, win_margin_type, win_margin, match_result
                    // all stay null: these fixtures have not been played.
                ]
            );

            $scheduledAt = $scheduledAt->copy()->addDays(2);
        }

        return $matches;
    }

    /**
     * Builds the playing-squad and innings-shell relationship chain for
     * one demo match only (Mumbai Indians vs Chennai Super Kings), to
     * verify the schema up to the scoring boundary. No deliveries.
     *
     * @param  array<string, EditionTeam>  $editionTeams
     * @param  array<string, Collection<int, TeamPlayer>>  $teamPlayers
     */
    private function seedMatchPlayersAndInnings(GameMatch $match, array $editionTeams, array $teamPlayers): void
    {
        $this->seedMatchPlayersForSide($match, $teamPlayers['Mumbai Indians'], captainName: 'Rohit Sharma', keeperName: null);
        $this->seedMatchPlayersForSide($match, $teamPlayers['Chennai Super Kings'], captainName: 'MS Dhoni', keeperName: 'MS Dhoni');

        Innings::firstOrCreate(
            ['match_id' => $match->id, 'innings_number' => 1],
            [
                'batting_team_id' => $editionTeams['Mumbai Indians']->id,
                'bowling_team_id' => $editionTeams['Chennai Super Kings']->id,
                'status' => 'scheduled',
                'legal_balls' => 0,
                'total_runs' => 0,
                'total_wickets' => 0,
                'extras' => 0,
            ]
        );

        Innings::firstOrCreate(
            ['match_id' => $match->id, 'innings_number' => 2],
            [
                'batting_team_id' => $editionTeams['Chennai Super Kings']->id,
                'bowling_team_id' => $editionTeams['Mumbai Indians']->id,
                'status' => 'scheduled',
                'legal_balls' => 0,
                'total_runs' => 0,
                'total_wickets' => 0,
                'extras' => 0,
            ]
        );
    }

    /**
     * @param  Collection<int, TeamPlayer>  $squad
     */
    private function seedMatchPlayersForSide(GameMatch $match, $squad, ?string $captainName, ?string $keeperName): void
    {
        foreach ($squad as $teamPlayer) {
            $playerName = $teamPlayer->playerRegistration->player->name;

            MatchPlayer::firstOrCreate(
                ['match_id' => $match->id, 'team_player_id' => $teamPlayer->id],
                [
                    'is_captain' => $playerName === $captainName,
                    'is_wicket_keeper' => $playerName === $keeperName,
                ]
            );
        }
    }

    /**
     * Development-only login accounts. These are NOT linked to any
     * Player record — players.user_id stays null for the seeded
     * cricketers, since a login is not needed for most players.
     *
     * Dev credentials (local/demo only):
     *   admin@rppl.test  / password
     *   scorer@rppl.test / password
     */
    private function seedDevelopmentUsers(): void
    {
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $scorerRole = Role::where('slug', 'scorer')->firstOrFail();

        User::firstOrCreate(
            ['email' => 'admin@rppl.test'],
            [
                'role_id' => $adminRole->id,
                'name' => 'RPPL Demo Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'scorer@rppl.test'],
            [
                'role_id' => $scorerRole->id,
                'name' => 'RPPL Demo Scorer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
