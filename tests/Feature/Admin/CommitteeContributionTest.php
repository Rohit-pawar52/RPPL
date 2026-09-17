<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommitteeContributionTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $scorerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $this->scorerRole = Role::create(['name' => 'Scorer', 'slug' => 'scorer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    private function scorer(): User
    {
        return User::factory()->create(['role_id' => $this->scorerRole->id]);
    }

    // ----- Committee member CRUD / authorization -----

    public function test_admin_can_manage_committee_members_and_scorer_is_forbidden(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.committee-members.store'), ['name' => 'Ravi Kumar', 'phone' => '9876500001'])
            ->assertRedirect(route('admin.committee-members.index'));

        $member = CommitteeMember::firstWhere('name', 'Ravi Kumar');
        $this->assertNotNull($member);
        $this->assertTrue($member->is_active);

        $this->actingAs($admin)->get(route('admin.committee-members.index'))->assertOk();

        $scorer = $this->scorer();
        $this->actingAs($scorer)->get(route('admin.committee-members.index'))->assertForbidden();
        $this->actingAs($scorer)
            ->post(route('admin.committee-members.store'), ['name' => 'Blocked', 'phone' => '1'])
            ->assertForbidden();
        $this->assertNull(CommitteeMember::firstWhere('name', 'Blocked'));
    }

    public function test_member_with_contribution_history_cannot_be_deleted_but_member_without_history_can(): void
    {
        $withHistory = CommitteeMember::factory()->create();
        EditionContribution::factory()->create(['committee_member_id' => $withHistory->id]);
        $withoutHistory = CommitteeMember::factory()->create();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.committee-members.destroy', $withHistory))
            ->assertRedirect(route('admin.committee-members.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('committee_members', ['id' => $withHistory->id]);

        $this->actingAs($admin)
            ->delete(route('admin.committee-members.destroy', $withoutHistory))
            ->assertRedirect(route('admin.committee-members.index'));
        $this->assertDatabaseMissing('committee_members', ['id' => $withoutHistory->id]);
    }

    // ----- Contribution creation + finance integration -----

    public function test_valid_contribution_creates_linked_income_transaction_and_enforces_minimum_amount(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create(['name' => 'Sunita Rao', 'is_active' => true]);
        $admin = $this->admin();

        // Below the ₹1000 minimum must be rejected, and must create nothing.
        $this->actingAs($admin)
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'source' => 'committee:'.$member->id,
                'amount' => '999.99',
                'contributed_at' => '2026-01-10',
            ])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, EditionContribution::count());
        $this->assertSame(0, EditionTransaction::count());

        // A valid contribution creates both rows, linked, with the
        // amount/date mirrored onto the finance transaction.
        $this->actingAs($admin)
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'source' => 'committee:'.$member->id,
                'amount' => '1000',
                'contributed_at' => '2026-01-10',
                'notes' => 'First contribution',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $contribution = EditionContribution::first();
        $this->assertNotNull($contribution);
        $this->assertSame($admin->id, $contribution->created_by);

        $transaction = EditionTransaction::first();
        $this->assertNotNull($transaction);
        $this->assertSame('income', $transaction->type);
        $this->assertSame('Committee Contribution', $transaction->category);
        $this->assertSame('1000.00', $transaction->amount);
        $this->assertSame($contribution->edition_transaction_id, $transaction->id);

        // The same member may contribute again later — no uniqueness rule.
        $this->actingAs($admin)
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'source' => 'committee:'.$member->id,
                'amount' => '5000',
                'contributed_at' => '2026-02-01',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(2, EditionContribution::where('committee_member_id', $member->id)->count());
    }

    public function test_inactive_member_cannot_receive_a_new_contribution(): void
    {
        $edition = Edition::factory()->create();
        $inactiveMember = CommitteeMember::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'source' => 'committee:'.$inactiveMember->id,
                'amount' => '2000',
                'contributed_at' => '2026-01-10',
            ])
            ->assertSessionHasErrors('source_id');

        $this->assertSame(0, EditionContribution::count());
    }

    // ----- Contribution delete -----

    public function test_deleting_a_contribution_atomically_removes_its_linked_transaction(): void
    {
        $contribution = EditionContribution::factory()->create();
        $transactionId = $contribution->edition_transaction_id;

        $this->actingAs($this->admin())
            ->delete(route('admin.edition-contributions.destroy', $contribution))
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertDatabaseMissing('edition_contributions', ['id' => $contribution->id]);
        $this->assertDatabaseMissing('edition_transactions', ['id' => $transactionId]);
    }

    // ----- Direct finance ledger protection -----

    public function test_transaction_linked_to_a_contribution_cannot_be_directly_updated_or_deleted(): void
    {
        $contribution = EditionContribution::factory()->create();
        $transaction = $contribution->transaction;
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.edition-transactions.edit', $transaction))
            ->assertRedirect(route('admin.edition-transactions.index'))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->put(route('admin.edition-transactions.update', $transaction), [
                'edition_id' => $transaction->edition_id,
                'type' => 'expense',
                'amount' => '1',
                'transaction_date' => '2026-01-01',
            ])
            ->assertSessionHas('error');
        $this->assertSame('income', $transaction->fresh()->type); // unchanged

        $this->actingAs($admin)
            ->delete(route('admin.edition-transactions.destroy', $transaction))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('edition_transactions', ['id' => $transaction->id]);

        // An ordinary, unlinked transaction is unaffected by this rule.
        $plainTransaction = EditionTransaction::factory()->create();
        $this->actingAs($admin)
            ->delete(route('admin.edition-transactions.destroy', $plainTransaction))
            ->assertRedirect(route('admin.edition-transactions.index'))
            ->assertSessionHas('success');
    }

    // ----- Filters / summary / edition integration -----

    public function test_contribution_filters_and_summary_totals_are_correct(): void
    {
        $editionA = Edition::factory()->create();
        $editionB = Edition::factory()->create();
        $memberA = CommitteeMember::factory()->create(['name' => 'Alpha Member']);
        $memberB = CommitteeMember::factory()->create(['name' => 'Beta Member']);

        EditionContribution::factory()->create(['edition_id' => $editionA->id, 'committee_member_id' => $memberA->id, 'amount' => '1000.00']);
        EditionContribution::factory()->create(['edition_id' => $editionA->id, 'committee_member_id' => $memberB->id, 'amount' => '2000.00']);
        EditionContribution::factory()->create(['edition_id' => $editionB->id, 'committee_member_id' => $memberA->id, 'amount' => '9000.00']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['edition_id' => $editionA->id]));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('contributions'));
        $this->assertEquals(3000.0, (float) $response->viewData('totalContributions'));

        $memberFiltered = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['committee_member_id' => $memberA->id]));
        $this->assertCount(2, $memberFiltered->viewData('contributions'));
    }

    public function test_edition_admin_page_shows_contribution_summary_and_link(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create();
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'committee_member_id' => $member->id, 'amount' => '4000.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('4,000.00');
        $response->assertSee(route('admin.edition-contributions.index', ['edition_id' => $edition->id]), false);
    }

    // ----- Public privacy -----

    /**
     * As of Phase 3.38B3 the public edition page shows a combined
     * contributor leaderboard (name, rank, and — deliberately, per that
     * phase's requirement — total contribution amount). This test
     * confirms names are aggregated (not repeated per row) and that
     * private fields never leak; the ranking algorithm itself (identity
     * combination, sorting, isolation) is covered by
     * ContributorRankingTest.
     */
    public function test_public_edition_page_shows_aggregated_contributor_totals_without_private_data(): void
    {
        $edition = Edition::factory()->create();
        $memberA = CommitteeMember::factory()->create(['name' => 'Public Contributor One', 'phone' => '9876543210']);
        $memberB = CommitteeMember::factory()->create(['name' => 'Public Contributor Two']);

        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $memberA->id,
            'amount' => '5432.10',
            'notes' => 'PRIVATE_NOTE_XYZ',
        ]);
        // Same member contributes again — name/total must appear once, combined.
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $memberA->id,
            'amount' => '1500.00',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $memberB->id,
            'amount' => '3000.00',
        ]);

        $response = $this->get(route('public.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Public Contributor One');
        $response->assertSee('Public Contributor Two');
        // Phase 3.40 superseded this: the public leaderboard no longer
        // renders any contribution amount at all (5432.10 + 1500.00 =
        // 6932.10 combined total is still used internally to RANK member
        // A first — see the position assertion below — but never shown).
        $response->assertDontSee('6,932');
        $response->assertDontSee('3,000');

        // Name appears exactly once, even though member A contributed twice.
        $this->assertSame(1, substr_count($response->getContent(), 'Public Contributor One'));

        $response->assertDontSee('9876543210');
        $response->assertDontSee('PRIVATE_NOTE_XYZ');
    }
}
