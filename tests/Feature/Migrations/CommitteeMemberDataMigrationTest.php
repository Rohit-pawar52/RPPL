<?php

namespace Tests\Feature\Migrations;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\User;
use App\Services\Finance\ContributorRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.48 — proves the data migration that collapsed CommitteeMember
 * into Contributor behaves correctly. RefreshDatabase already runs
 * every migration (including this one) before each test starts, so by
 * the time a test method runs, the migration has already executed
 * against an EMPTY database — there is no "before" state left. These
 * tests instead insert old-shape rows and call the migration's own
 * up() logic a second time against them, proving its actual
 * transformation (identity mapping, no accidental name-based merge, no
 * duplication, membership reconstruction, amount/date/transaction-link
 * preservation) directly and repeatably — complementing the one-off
 * disposable-SQLite dry run recorded in the Phase 3.48 report, which
 * exercised the real end-to-end `migrate` flow with real seeded data.
 */
class CommitteeMemberDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigrationAgain(): void
    {
        $migration = require database_path('migrations/2026_09_23_165243_migrate_committee_members_into_contributors.php');
        $migration->up();
    }

    public function test_explicitly_linked_committee_member_reuses_its_existing_contributor(): void
    {
        $member = CommitteeMember::factory()->create(['name' => 'Linked Person']);
        $contributor = Contributor::factory()->create(['name' => 'Linked Person', 'committee_member_id' => $member->id]);

        $this->runMigrationAgain();

        $this->assertSame(1, Contributor::where('name', 'Linked Person')->count());
        $this->assertSame($contributor->id, Contributor::where('name', 'Linked Person')->first()->id);
    }

    public function test_unlinked_committee_member_gets_exactly_one_new_contributor_and_is_idempotent(): void
    {
        $member = CommitteeMember::factory()->create(['name' => 'Unlinked Person', 'phone' => '9990001111']);

        $this->runMigrationAgain();

        $contributor = Contributor::where('committee_member_id', $member->id)->first();
        $this->assertNotNull($contributor);
        $this->assertSame('Unlinked Person', $contributor->name);
        $this->assertSame('9990001111', $contributor->phone);

        // Idempotent: running it again must not create a second one.
        $this->runMigrationAgain();
        $this->assertSame(1, Contributor::where('committee_member_id', $member->id)->count());
    }

    public function test_an_unlinked_committee_member_is_never_merged_by_name_with_an_existing_unrelated_contributor(): void
    {
        $member = CommitteeMember::factory()->create(['name' => 'Same Name']);
        $existingContributor = Contributor::factory()->create(['name' => 'Same Name', 'committee_member_id' => null]);

        $this->runMigrationAgain();

        // A NEW contributor must have been created for the committee
        // member — never merged into the unrelated same-named row.
        $this->assertSame(2, Contributor::where('name', 'Same Name')->count());
        $newContributor = Contributor::where('committee_member_id', $member->id)->first();
        $this->assertNotNull($newContributor);
        $this->assertNotSame($existingContributor->id, $newContributor->id);
    }

    public function test_edition_membership_is_reconstructed_from_committee_sourced_contributions(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create();
        $admin = User::factory()->create();
        $transaction = EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income']);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $member->id,
            'contributor_id' => null,
            'edition_transaction_id' => $transaction->id,
            'created_by' => $admin->id,
        ]);

        $this->runMigrationAgain();

        $contributor = Contributor::where('committee_member_id', $member->id)->firstOrFail();
        $this->assertDatabaseHas('edition_committee_members', ['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);
    }

    public function test_a_committee_member_with_no_contributions_gets_no_membership_row(): void
    {
        $member = CommitteeMember::factory()->create();

        $this->runMigrationAgain();

        $contributor = Contributor::where('committee_member_id', $member->id)->firstOrFail();
        $this->assertDatabaseMissing('edition_committee_members', ['contributor_id' => $contributor->id]);
    }

    public function test_contribution_amount_date_and_transaction_link_are_preserved_never_duplicated(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create();
        $admin = User::factory()->create();
        $transaction = EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => '4321.00']);
        $contribution = EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $member->id,
            'contributor_id' => null,
            'edition_transaction_id' => $transaction->id,
            'amount' => '4321.00',
            'contributed_at' => '2026-02-01',
            'created_by' => $admin->id,
        ]);

        $countBefore = EditionContribution::count();
        $transactionCountBefore = EditionTransaction::count();

        $this->runMigrationAgain();

        $this->assertSame($countBefore, EditionContribution::count(), 'no contribution row is created or removed');
        $this->assertSame($transactionCountBefore, EditionTransaction::count(), 'no transaction row is created or removed');

        $contribution->refresh();
        $this->assertSame('4321.00', $contribution->amount);
        $this->assertSame('2026-02-01', $contribution->contributed_at->format('Y-m-d'));
        $this->assertSame($transaction->id, $contribution->edition_transaction_id);
        $this->assertNotNull($contribution->contributor_id);
    }

    public function test_ranking_total_is_preserved_after_migration(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create(['name' => 'Ranked Person']);
        $admin = User::factory()->create();
        foreach (['1000.00', '2000.00'] as $amount) {
            $transaction = EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => $amount]);
            EditionContribution::factory()->create([
                'edition_id' => $edition->id,
                'committee_member_id' => $member->id,
                'contributor_id' => null,
                'edition_transaction_id' => $transaction->id,
                'amount' => $amount,
                'created_by' => $admin->id,
            ]);
        }

        $this->runMigrationAgain();

        $ranking = app(ContributorRankingService::class)->getEditionRanking($edition);
        $row = collect($ranking)->firstWhere('name', 'Ranked Person');
        $this->assertNotNull($row);
        $this->assertSame(3000.0, $row['total_amount']);
    }
}
