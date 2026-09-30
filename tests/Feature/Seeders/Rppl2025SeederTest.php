<?php

namespace Tests\Feature\Seeders;

use App\Models\Delivery;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\GameMatch\MatchResultService;
use App\Services\Statistics\StandingsService;
use Database\Seeders\Demo\DemoSettingSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Rppl2025\Rppl2025EditionSeeder;
use Database\Seeders\Rppl2025\Rppl2025FixtureSeeder;
use Database\Seeders\Rppl2025\Rppl2025RegistrationAndSquadSeeder;
use Database\Seeders\Rppl2026\Rppl2026EditionSeeder;
use Database\Seeders\Rppl2026\Rppl2026FixtureSeeder;
use Database\Seeders\Rppl2026\Rppl2026PlayerSeeder;
use Database\Seeders\Rppl2026\Rppl2026RegistrationAndSquadSeeder;
use Database\Seeders\Rppl2026\Rppl2026TeamAndVenueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The historical RPPL 2025 season must be a complete, internally consistent
 * completed tournament, fully separate from the live RPPL 2026 data.
 */
class Rppl2025SeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedSeason(): void
    {
        foreach ([
            RoleSeeder::class,
            DemoSettingSeeder::class,
            DemoUserSeeder::class,
            Rppl2026PlayerSeeder::class,
            Rppl2026TeamAndVenueSeeder::class,
            Rppl2026EditionSeeder::class,
            Rppl2026RegistrationAndSquadSeeder::class,
            Rppl2026FixtureSeeder::class,
            Rppl2025EditionSeeder::class,
            Rppl2025RegistrationAndSquadSeeder::class,
            Rppl2025FixtureSeeder::class,
        ] as $seeder) {
            $this->seed($seeder);
        }
    }

    public function test_2025_season_is_a_complete_consistent_completed_tournament(): void
    {
        $this->seedSeason();

        $edition = Edition::where('year', 2025)->sole();
        $edition2026 = Edition::where('year', 2026)->sole();
        $this->assertSame('completed', $edition->status);

        // Separate participation rows from 2026.
        $teams = EditionTeam::where('edition_id', $edition->id)->get();
        $this->assertCount(4, $teams);
        $this->assertEmpty($teams->pluck('id')->intersect(EditionTeam::where('edition_id', $edition2026->id)->pluck('id')));

        // Squads and registrations.
        $this->assertSame(52, TeamPlayer::whereIn('edition_team_id', $teams->pluck('id'))->count());
        $registrations = PlayerRegistration::where('edition_id', $edition->id)->get();
        $this->assertCount(55, $registrations);
        $this->assertSame(52, $registrations->where('payment_status', 'paid')->count());
        $this->assertEqualsCanonicalizing(['refunded', 'failed', 'refunded'], $registrations->where('payment_status', '!=', 'paid')->pluck('payment_status')->all());
        foreach ($registrations as $registration) {
            $this->assertSame(PlayerRegistration::formatRegistrationNumber(2025, $registration->id), $registration->registration_number);
            $this->assertSame(2025, $registration->registered_at->year);
        }

        // Matches.
        $matches = GameMatch::where('edition_id', $edition->id)->orderBy('match_number')->get();
        $this->assertCount(7, $matches);
        $this->assertSame(6, $matches->where('match_stage', 'league')->count());
        $this->assertSame(1, $matches->where('match_stage', 'final')->count());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $matches->pluck('match_number')->all());
        $this->assertTrue($matches->every(fn ($m) => $m->match_status === 'completed' && (int) $m->overs_per_innings === 5));
        $this->assertGreaterThan(1, $matches->pluck('toss_decision')->unique()->count());
        $this->assertGreaterThan(1, $matches->pluck('toss_winner_team_id')->unique()->count());

        $results = app(MatchResultService::class);

        foreach ($matches as $match) {
            $innings = $match->innings()->orderBy('innings_number')->get();
            $this->assertCount(2, $innings);

            foreach ($innings as $inning) {
                $deliveries = Delivery::where('innings_id', $inning->id)->get();

                $this->assertSame((int) $inning->total_runs, (int) $deliveries->sum('total_runs'));
                $this->assertLessThanOrEqual(10, $inning->total_wickets);
                $this->assertSame((int) $inning->total_wickets, $deliveries->where('is_wicket', true)->count());
                $this->assertLessThanOrEqual(30, $inning->legal_balls);
                $this->assertSame((int) $inning->legal_balls, $deliveries->where('is_legal_delivery', true)->count());

                $oversPerBowler = $deliveries->groupBy('bowler_match_player_id')->map(fn ($rows) => $rows->pluck('over_number')->unique()->count());
                $this->assertTrue($oversPerBowler->every(fn ($overs) => $overs <= 1), 'A bowler bowled more than one over in match '.$match->match_number);
            }

            // Every player in a match comes from this edition's own squads.
            $this->assertTrue($match->matchPlayers()->with('teamPlayer.editionTeam')->get()
                ->every(fn ($mp) => $mp->teamPlayer->editionTeam->edition_id === $edition->id));

            $expected = $results->calculateResult($innings[0]->load('battingTeam.team'), $innings[1]->load('battingTeam.team'));
            $this->assertSame('won', $match->result_type);
            $this->assertSame($expected['winner_team_id'], $match->winner_team_id);
            $this->assertSame($expected['match_result'], $match->match_result);
        }

        // Standings count all 7 decided matches (2 points each).
        $standings = app(StandingsService::class)->getEditionStandings($edition)['standings'];
        $this->assertSame(2 * 7, array_sum(array_column($standings, 'points')));

        // The Final is contested by the league's (unambiguous) top two.
        $leagueWins = $matches->where('match_stage', 'league')->pluck('winner_team_id')->countBy()->sortDesc();
        $this->assertGreaterThan($leagueWins->values()[2], $leagueWins->values()[1]);

        $final = $matches->firstWhere('match_stage', 'final');
        $this->assertEqualsCanonicalizing($leagueWins->keys()->take(2)->all(), [$final->edition_team_a_id, $final->edition_team_b_id]);
        $this->assertNotNull($final->winner_team_id);
    }

    public function test_rerunning_the_2025_seeders_creates_nothing_and_leaves_2026_untouched(): void
    {
        $this->seedSeason();

        $edition2026 = Edition::where('year', 2026)->sole();
        $counts = fn () => [
            PlayerRegistration::count(),
            TeamPlayer::count(),
            GameMatch::count(),
            Delivery::count(),
        ];
        $before = $counts();

        $this->seed(Rppl2025RegistrationAndSquadSeeder::class);
        $this->seed(Rppl2025FixtureSeeder::class);

        $this->assertSame($before, $counts());
        $this->assertSame(6, GameMatch::where('edition_id', $edition2026->id)->count());
        $this->assertSame(60, TeamPlayer::whereHas('editionTeam', fn ($q) => $q->where('edition_id', $edition2026->id))->count());
    }
}
