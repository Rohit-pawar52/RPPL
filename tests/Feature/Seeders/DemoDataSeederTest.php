<?php

namespace Tests\Feature\Seeders;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTeam;
use App\Models\EditionTransaction;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\ContributorRankingService;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One focused test proving the stakeholder demo dataset's high-value
 * invariants hold — NOT a row-by-row check of every seeded record. This
 * is the safe verification step required before `migrate:fresh --seed`
 * is ever run against the real dev database: it runs the exact same
 * DatabaseSeeder, but against phpunit's in-memory SQLite connection
 * (see phpunit.xml), which can never touch real data.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dataset_seeds_a_complete_internally_consistent_environment(): void
    {
        $this->seed();

        $this->assertDemoUsersExist();
        $this->assertEditionsAreConsistent();
        $this->assertMasterDataIsPopulated();
        $this->assertActiveEditionHasRegistrationsAndSquads();
        $this->assertMatchLifecycleStatesArePresent();
        $this->assertFinanceAndContributionsAreConsistent();
        $this->assertReportingServicesCanConsumeTheSeededData();
    }

    private function assertDemoUsersExist(): void
    {
        $admin = User::where('email', 'admin@rppl.test')->first();
        $scorer = User::where('email', 'scorer@rppl.test')->first();

        $this->assertNotNull($admin);
        $this->assertNotNull($scorer);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($scorer->is_active);
        $this->assertSame('admin', $admin->role->slug);
        $this->assertSame('scorer', $scorer->role->slug);
    }

    private function assertEditionsAreConsistent(): void
    {
        $this->assertSame(3, Edition::count());

        $active = Edition::where('status', 'active')->first();
        $this->assertNotNull($active);
        $this->assertTrue((bool) $active->registration_open);

        // At most one edition may ever be open for public registration.
        $this->assertSame(1, Edition::where('registration_open', true)->count());

        $this->assertSame(1, Edition::where('status', 'completed')->count());
        $this->assertSame(1, Edition::where('status', 'upcoming')->count());
    }

    private function assertMasterDataIsPopulated(): void
    {
        $this->assertGreaterThanOrEqual(40, Player::count());
        $this->assertSame(6, Team::count());
        $this->assertSame(3, Venue::count());
    }

    private function assertActiveEditionHasRegistrationsAndSquads(): void
    {
        $active = Edition::where('status', 'active')->firstOrFail();

        $this->assertGreaterThan(0, PlayerRegistration::where('edition_id', $active->id)->count());

        $editionTeamIds = EditionTeam::where('edition_id', $active->id)->pluck('id');
        $this->assertSame(6, $editionTeamIds->count());

        $squadSize = TeamPlayer::whereIn('edition_team_id', $editionTeamIds)->count();
        $this->assertGreaterThanOrEqual(6 * 6, $squadSize);

        // Every seeded registration was created without a payment proof
        // and without ever dispatching OCR — ocr_status must reflect
        // that conclusively, never sit at the migration's raw 'pending'
        // default as if a job were still queued.
        $this->assertSame(0, PlayerRegistration::where('ocr_status', 'pending')->count());
    }

    private function assertMatchLifecycleStatesArePresent(): void
    {
        $active = Edition::where('status', 'active')->firstOrFail();
        $matches = GameMatch::where('edition_id', $active->id)->get()->keyBy('match_status');

        foreach (['scheduled', 'toss', 'live', 'completed', 'abandoned', 'cancelled'] as $status) {
            $this->assertTrue($matches->has($status), "Expected a demo match with match_status={$status}.");
        }

        $this->assertSame(1, GameMatch::where('edition_id', $active->id)->where('match_status', 'live')->count());

        $completed = GameMatch::where('edition_id', $active->id)->where('match_status', 'completed')->get();
        $this->assertGreaterThanOrEqual(1, $completed->count());

        foreach ($completed as $match) {
            $first = $match->firstInnings;
            $second = $match->secondInnings;

            $this->assertNotNull($first);
            $this->assertNotNull($second);
            $this->assertSame('completed', $first->status);
            $this->assertSame('completed', $second->status);
            $this->assertGreaterThan(0, $first->deliveries()->count());
            $this->assertGreaterThan(0, $second->deliveries()->count());
            $this->assertNotNull($match->result_type);
        }

        $live = $matches->get('live');
        $this->assertNotNull($live->firstInnings);
        $this->assertSame('live', $live->firstInnings->status);
        $this->assertGreaterThan(0, $live->firstInnings->deliveries()->count());
        $this->assertNull($live->result_type);

        $abandoned = $matches->get('abandoned');
        $this->assertNotNull($abandoned->firstInnings);
        $this->assertGreaterThan(0, $abandoned->firstInnings->deliveries()->count());

        $this->assertSame(2, GameMatch::where('edition_id', Edition::where('status', 'completed')->firstOrFail()->id)->where('match_status', 'completed')->count());
    }

    private function assertFinanceAndContributionsAreConsistent(): void
    {
        $active = Edition::where('status', 'active')->firstOrFail();

        $this->assertGreaterThan(0, EditionTransaction::where('edition_id', $active->id)->count());
        $this->assertGreaterThan(0, EditionContribution::where('edition_id', $active->id)->count());

        // Every contribution's linked transaction must exist and carry
        // exactly the same amount — the one real consistency invariant
        // EditionContributionService owns.
        EditionContribution::where('edition_id', $active->id)->get()->each(function (EditionContribution $contribution) {
            $transaction = $contribution->transaction;

            $this->assertNotNull($transaction);
            $this->assertSame('income', $transaction->type);
            $this->assertEquals((float) $contribution->amount, (float) $transaction->amount);
        });

        $this->assertGreaterThanOrEqual(10, CommitteeMember::count());
        $this->assertGreaterThanOrEqual(12, Contributor::count());
    }

    private function assertReportingServicesCanConsumeTheSeededData(): void
    {
        $active = Edition::where('status', 'active')->firstOrFail();

        $ranking = app(ContributorRankingService::class)->getEditionRanking($active);
        $this->assertGreaterThanOrEqual(10, count($ranking));

        $summary = EditionTransaction::summaryForEdition($active->id);
        $this->assertGreaterThan(0, $summary['income']);

        // Must not throw for a seeded edition with real completed-match
        // Delivery data — the same services the Dashboard/Reports/public
        // pages call.
        $standings = app(StandingsService::class)->getEditionStandings($active);
        $this->assertIsArray($standings);

        $leaderboard = app(PlayerStatisticsService::class)->getEditionLeaderboard($active);
        $this->assertIsArray($leaderboard);
    }
}
