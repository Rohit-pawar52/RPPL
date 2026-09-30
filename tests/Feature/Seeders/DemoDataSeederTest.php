<?php

namespace Tests\Feature\Seeders;

use App\Models\Contributor;
use App\Models\Delivery;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionContribution;
use App\Models\EditionTeam;
use App\Models\EditionTransaction;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\Statistics\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One focused test proving the RPPL 2026 local UAT dataset's high-value
 * invariants hold — NOT a row-by-row check of every seeded record. This
 * is the safe verification step required before `migrate:fresh --seed`
 * is ever run against the real dev database: it runs the exact same
 * DatabaseSeeder, but against phpunit's in-memory SQLite connection
 * (see phpunit.xml), which can never touch real data.
 *
 * Replaces the previous 3-edition/6-generic-team dataset's assertions
 * with the RPPL 2026 reset's single-edition/4-IPL-style-team shape — see
 * that reset's completion report for why DatabaseSeeder itself changed.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_dataset_seeds_a_complete_internally_consistent_environment(): void
    {
        $this->seed();

        $this->assertDemoUsersExist();
        $this->assertTwoEditionsSharingFourTeams();
        $this->assertSixtyPlayersRegisteredAndSquadded();
        $this->assertSixFixturesWithThreeCompletedAndThreeScheduled();
        $this->assertCompletedMatchesHaveConsistentBallByBallData();
        $this->assertStandingsDeriveFromSeededResults();
        $this->assertSmallFinanceDatasetExists();
        $this->assertDemoNotificationMastersExistWithNoFakeSendHistory();
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

    /**
     * The active RPPL 2026 season (this test's original subject) — every
     * 2026 assertion below is scoped to it, because the completed RPPL
     * 2025 season now lives alongside it (see Rppl2025SeederTest).
     */
    private function edition2026(): Edition
    {
        return Edition::where('year', 2026)->firstOrFail();
    }

    private function assertTwoEditionsSharingFourTeams(): void
    {
        $this->assertSame(2, Edition::count());

        $edition = $this->edition2026();
        $this->assertSame('RPPL 2026', $edition->name);
        $this->assertSame('active', $edition->status);

        $previous = Edition::where('year', 2025)->firstOrFail();
        $this->assertSame('RPPL 2025', $previous->name);
        $this->assertSame('completed', $previous->status);

        $this->assertSame(4, Team::count());
        $this->assertSame(4, EditionTeam::where('edition_id', $edition->id)->count());
        $this->assertSame(4, EditionTeam::where('edition_id', $previous->id)->count());
        // Same master teams, but each season has its own participation rows.
        $this->assertSame([], EditionTeam::where('edition_id', $previous->id)->pluck('id')->intersect(EditionTeam::where('edition_id', $edition->id)->pluck('id'))->all());

        $this->assertEqualsCanonicalizing(
            ['MI', 'RCB', 'CSK', 'KKR'],
            Team::pluck('short_name')->all(),
        );
    }

    private function assertSixtyPlayersRegisteredAndSquadded(): void
    {
        // 60 squad players plus 4 extra 2026 registrants who are not (yet)
        // squadded and sit in pending/failed/refunded payment states.
        $this->assertSame(64, Player::count());

        $edition = $this->edition2026();
        $this->assertSame(64, PlayerRegistration::where('edition_id', $edition->id)->count());
        $this->assertSame(60, TeamPlayer::whereIn('edition_team_id', EditionTeam::where('edition_id', $edition->id)->pluck('id'))->count());
        $this->assertSame(2, PlayerRegistration::where('edition_id', $edition->id)->where('payment_status', 'pending')->count());

        // Every seeded registration was created without a payment proof
        // and without ever dispatching OCR — ocr_status must reflect
        // that conclusively, never sit at the migration's raw 'pending'
        // default as if a job were still queued.
        $this->assertSame(0, PlayerRegistration::where('ocr_status', 'pending')->count());
        $this->assertSame(0, PlayerRegistration::whereNotNull('aadhaar_document_path')->count());
        $this->assertSame(0, PlayerRegistration::whereNotNull('payment_proof_path')->count());

        foreach (EditionTeam::where('edition_id', $edition->id)->get() as $editionTeam) {
            $this->assertSame(15, TeamPlayer::where('edition_team_id', $editionTeam->id)->count());
        }
    }

    private function assertSixFixturesWithThreeCompletedAndThreeScheduled(): void
    {
        $edition = $this->edition2026();

        $this->assertSame(6, GameMatch::where('edition_id', $edition->id)->count());
        $this->assertSame(3, GameMatch::where('edition_id', $edition->id)->where('match_status', 'completed')->count());
        $this->assertSame(3, GameMatch::where('edition_id', $edition->id)->where('match_status', 'scheduled')->count());

        // No Final yet — the league-stage Top 2 isn't known with only 3
        // of 6 league matches played (see the reset completion report).
        $this->assertSame(0, GameMatch::where('edition_id', $edition->id)->where('match_stage', 'final')->count());

        GameMatch::where('edition_id', $edition->id)->get()->each(function (GameMatch $match) {
            $this->assertSame(5, $match->overs_per_innings);
        });
    }

    private function assertCompletedMatchesHaveConsistentBallByBallData(): void
    {
        $completed = GameMatch::where('edition_id', $this->edition2026()->id)->where('match_status', 'completed')->get();
        $this->assertCount(3, $completed);

        foreach ($completed as $match) {
            $first = $match->firstInnings;
            $second = $match->secondInnings;

            $this->assertNotNull($first);
            $this->assertNotNull($second);
            $this->assertSame('completed', $first->status);
            $this->assertSame('completed', $second->status);
            $this->assertNotNull($match->result_type);
            $this->assertNotNull($match->winner_team_id);

            // Exactly 11 selected players per side — the frozen S02 rule
            // this whole dataset was built to exercise.
            $this->assertSame(11, MatchPlayer::where('match_id', $match->id)
                ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $match->edition_team_a_id))
                ->count());
            $this->assertSame(11, MatchPlayer::where('match_id', $match->id)
                ->whereHas('teamPlayer', fn ($q) => $q->where('edition_team_id', $match->edition_team_b_id))
                ->count());

            foreach ([$first, $second] as $innings) {
                $deliveryCount = Delivery::where('innings_id', $innings->id)->count();
                $this->assertGreaterThan(0, $deliveryCount, "Innings {$innings->id} has no Delivery rows.");

                // Innings.total_runs is a cache rebuilt from Delivery rows
                // (DeliveryService::recalculateInningsTotals()) — this
                // must still agree with a fresh independent SUM, proving
                // the cache was never hand-set.
                $sumFromDeliveries = (int) Delivery::where('innings_id', $innings->id)->sum('total_runs');
                $this->assertSame($sumFromDeliveries, $innings->fresh()->total_runs);
            }
        }
    }

    private function assertStandingsDeriveFromSeededResults(): void
    {
        $edition = $this->edition2026();

        // Must not throw for a seeded edition with real completed-match
        // Delivery data — the same service the public standings page and
        // dashboard call.
        $result = app(StandingsService::class)->getEditionStandings($edition);

        $this->assertIsArray($result);
        $this->assertCount(4, $result['standings']);
        $this->assertSame(0, $result['ignored_matches_count']);

        $totalPoints = array_sum(array_column($result['standings'], 'points'));
        // 3 completed league matches, each awarding points to two teams
        // (win/loss, or 1 each on a no-result) — never zero.
        $this->assertGreaterThan(0, $totalPoints);
    }

    private function assertSmallFinanceDatasetExists(): void
    {
        $edition = $this->edition2026();

        $this->assertGreaterThan(0, EditionTransaction::where('edition_id', $edition->id)->count());
        $this->assertGreaterThan(0, EditionContribution::where('edition_id', $edition->id)->count());
        $this->assertGreaterThan(0, EditionCommitteeMember::where('edition_id', $edition->id)->count());
        $this->assertGreaterThan(0, Contributor::count());

        EditionContribution::where('edition_id', $edition->id)->get()->each(function (EditionContribution $contribution) {
            $transaction = $contribution->transaction;

            $this->assertNotNull($transaction);
            $this->assertSame('income', $transaction->type);
            $this->assertEquals((float) $contribution->amount, (float) $transaction->amount);
        });
    }

    /**
     * Demo notification masters exist, but with deliberately ZERO send
     * history: seeding a fake "accepted" count would misrepresent a real
     * Firebase result that never happened.
     */
    private function assertDemoNotificationMastersExistWithNoFakeSendHistory(): void
    {
        $this->assertGreaterThanOrEqual(2, Notification::count());
        $this->assertSame(0, NotificationSend::count());

        Notification::all()->each(function (Notification $notification) {
            $this->assertNotNull($notification->created_by);
            $this->assertTrue($notification->creator->is_active);
        });
    }
}
