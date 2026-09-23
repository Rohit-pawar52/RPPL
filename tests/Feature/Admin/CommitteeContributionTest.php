<?php

namespace Tests\Feature\Admin;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\CommitteeDuesService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.48 — committee membership (edition-specific, on a Contributor)
 * and committee dues (target/paid/remaining/status), plus the
 * contribution-recording/finance-integration behavior that applies to a
 * committee member specifically. General (non-committee) contribution
 * recording is covered by GeneralContributionTest; Table UX (pagination/
 * sort/date-range/selected-export/header-rendering) on the Contributions
 * index is unchanged from before this phase and stays in the sections
 * below unmodified.
 */
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

    // ----- Committee membership management -----

    public function test_admin_can_add_and_remove_a_committee_member_and_scorer_is_forbidden(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create(['name' => 'Ravi Kumar']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.finance.committee.store'), ['edition_id' => $edition->id, 'contributor_id' => $contributor->id])
            ->assertRedirect(route('admin.finance.committee', ['edition_id' => $edition->id]));

        $this->assertDatabaseHas('edition_committee_members', ['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);

        $membership = EditionCommitteeMember::first();

        $this->actingAs($admin)
            ->delete(route('admin.finance.committee.destroy', $membership))
            ->assertRedirect(route('admin.finance.committee', ['edition_id' => $edition->id]));

        $this->assertDatabaseMissing('edition_committee_members', ['id' => $membership->id]);

        $scorer = $this->scorer();
        $this->actingAs($scorer)->get(route('admin.finance.committee'))->assertForbidden();
        $this->actingAs($scorer)
            ->post(route('admin.finance.committee.store'), ['edition_id' => $edition->id, 'contributor_id' => $contributor->id])
            ->assertForbidden();
    }

    public function test_adding_an_already_current_member_is_idempotent(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();

        $this->actingAs($this->admin())->post(route('admin.finance.committee.store'), [
            'edition_id' => $edition->id, 'contributor_id' => $contributor->id,
        ]);
        $this->actingAs($this->admin())->post(route('admin.finance.committee.store'), [
            'edition_id' => $edition->id, 'contributor_id' => $contributor->id,
        ]);

        $this->assertSame(1, EditionCommitteeMember::where('edition_id', $edition->id)->where('contributor_id', $contributor->id)->count());
    }

    public function test_removing_a_member_with_contribution_history_for_that_edition_is_blocked(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);

        $membership = EditionCommitteeMember::first();

        $this->actingAs($this->admin())
            ->delete(route('admin.finance.committee.destroy', $membership))
            ->assertRedirect(route('admin.finance.committee', ['edition_id' => $edition->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('edition_committee_members', ['id' => $membership->id]);
    }

    public function test_removing_membership_never_touches_the_contributor_or_their_history_in_other_editions(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $contributor = Contributor::factory()->create();
        EditionCommitteeMember::create(['edition_id' => $editionOne->id, 'contributor_id' => $contributor->id]);
        EditionCommitteeMember::create(['edition_id' => $editionTwo->id, 'contributor_id' => $contributor->id]);

        $membership = EditionCommitteeMember::where('edition_id', $editionOne->id)->first();

        $this->actingAs($this->admin())->delete(route('admin.finance.committee.destroy', $membership));

        $this->assertDatabaseHas('contributors', ['id' => $contributor->id]);
        $this->assertDatabaseHas('edition_committee_members', ['edition_id' => $editionTwo->id, 'contributor_id' => $contributor->id]);
    }

    public function test_copy_previous_edition_committee_is_idempotent_and_reports_counts(): void
    {
        $previous = Edition::factory()->create(['year' => 2025]);
        $current = Edition::factory()->create(['year' => 2026]);

        $alreadyOnBoth = Contributor::factory()->create();
        $onlyPrevious1 = Contributor::factory()->create();
        $onlyPrevious2 = Contributor::factory()->create();

        EditionCommitteeMember::create(['edition_id' => $previous->id, 'contributor_id' => $alreadyOnBoth->id]);
        EditionCommitteeMember::create(['edition_id' => $previous->id, 'contributor_id' => $onlyPrevious1->id]);
        EditionCommitteeMember::create(['edition_id' => $previous->id, 'contributor_id' => $onlyPrevious2->id]);
        EditionCommitteeMember::create(['edition_id' => $current->id, 'contributor_id' => $alreadyOnBoth->id]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.finance.committee.copy-previous'), ['edition_id' => $current->id])
            ->assertRedirect(route('admin.finance.committee', ['edition_id' => $current->id]));

        $response->assertSessionHas('success', '2 committee member(s) added, 1 already existed.');
        $this->assertSame(3, EditionCommitteeMember::where('edition_id', $current->id)->count());

        // Running it again adds nothing further.
        $this->actingAs($this->admin())->post(route('admin.finance.committee.copy-previous'), ['edition_id' => $current->id]);
        $this->assertSame(3, EditionCommitteeMember::where('edition_id', $current->id)->count());
    }

    public function test_copy_previous_never_copies_contributions_only_membership(): void
    {
        $previous = Edition::factory()->create(['year' => 2025]);
        $current = Edition::factory()->create(['year' => 2026]);
        $contributor = Contributor::factory()->create();
        EditionCommitteeMember::create(['edition_id' => $previous->id, 'contributor_id' => $contributor->id]);
        EditionContribution::factory()->create(['edition_id' => $previous->id, 'contributor_id' => $contributor->id, 'amount' => '5000.00']);

        $this->actingAs($this->admin())->post(route('admin.finance.committee.copy-previous'), ['edition_id' => $current->id]);

        $this->assertSame(0, EditionContribution::where('edition_id', $current->id)->count());
    }

    public function test_the_oldest_edition_has_no_previous_edition_to_copy_from(): void
    {
        $edition = Edition::factory()->create(['year' => 2020]);

        $this->actingAs($this->admin())
            ->post(route('admin.finance.committee.copy-previous'), ['edition_id' => $edition->id])
            ->assertRedirect(route('admin.finance.committee', ['edition_id' => $edition->id]))
            ->assertSessionHas('error');
    }

    // ----- Committee dues -----

    public function test_dues_status_reflects_installments_up_to_and_beyond_the_target(): void
    {
        app(SettingsService::class)->set('finance.committee_minimum_contribution', 1000.00);
        $edition = Edition::factory()->create();

        $notPaid = Contributor::factory()->create(['name' => 'Not Paid']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $notPaid->id]);

        $partial = Contributor::factory()->create(['name' => 'Partial Payer']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $partial->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $partial->id, 'amount' => '300.00']);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $partial->id, 'amount' => '200.00']);

        $fullPayer = Contributor::factory()->create(['name' => 'Full Payer']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $fullPayer->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $fullPayer->id, 'amount' => '1000.00']);

        $overPayer = Contributor::factory()->create(['name' => 'Over Payer']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $overPayer->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $overPayer->id, 'amount' => '1500.00']);

        $dues = app(CommitteeDuesService::class)->duesForEdition($edition);
        $byName = collect($dues)->keyBy(fn ($row) => $row['contributor']->name);

        $this->assertSame('not paid', $byName['Not Paid']['status']);
        $this->assertSame(0.0, $byName['Not Paid']['paid']);
        $this->assertSame(1000.0, $byName['Not Paid']['remaining']);

        $this->assertSame('partially paid', $byName['Partial Payer']['status']);
        $this->assertSame(500.0, $byName['Partial Payer']['paid']);
        $this->assertSame(500.0, $byName['Partial Payer']['remaining']);

        $this->assertSame('paid in full', $byName['Full Payer']['status']);
        $this->assertSame(0.0, $byName['Full Payer']['remaining']);

        // Overpayment is never an error — remaining floors at 0, still "paid in full".
        $this->assertSame('paid in full', $byName['Over Payer']['status']);
        $this->assertSame(1500.0, $byName['Over Payer']['paid']);
        $this->assertSame(0.0, $byName['Over Payer']['remaining']);
    }

    public function test_changing_the_global_target_changes_every_editions_displayed_dues(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('finance.committee_minimum_contribution', 1000.00);

        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id, 'amount' => '600.00']);

        $before = app(CommitteeDuesService::class)->duesFor($contributor, $edition);
        $this->assertSame('partially paid', $before['status']);

        $settings->set('finance.committee_minimum_contribution', 500.00);

        $after = app(CommitteeDuesService::class)->duesFor($contributor, $edition);
        $this->assertSame('paid in full', $after['status']);
        $this->assertSame(500.0, $after['target']);
    }

    public function test_dues_preview_endpoint_reports_committee_status_and_figures(): void
    {
        app(SettingsService::class)->set('finance.committee_minimum_contribution', 1000.00);
        $edition = Edition::factory()->create();
        $member = Contributor::factory()->create();
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $member->id]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $member->id, 'amount' => '400.00']);

        $nonMember = Contributor::factory()->create();

        $memberResponse = $this->actingAs($this->admin())
            ->getJson(route('admin.edition-contributions.dues-preview', ['edition_id' => $edition->id, 'contributor_id' => $member->id]));

        $memberResponse->assertOk()->assertJson(['is_committee_member' => true]);
        $memberResponse->assertJsonPath('dues.status', 'partially paid');

        $nonMemberResponse = $this->actingAs($this->admin())
            ->getJson(route('admin.edition-contributions.dues-preview', ['edition_id' => $edition->id, 'contributor_id' => $nonMember->id]));

        $nonMemberResponse->assertOk()->assertJson(['is_committee_member' => false]);
    }

    // ----- Contribution creation + finance integration for a committee member -----

    public function test_valid_committee_contribution_creates_linked_income_transaction_with_committee_category(): void
    {
        $edition = Edition::factory()->create();
        $member = Contributor::factory()->create(['name' => 'Sunita Rao']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $member->id]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $member->id,
                'amount' => '1000',
                'contributed_at' => '2026-01-10',
                'notes' => 'First contribution',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $contribution = EditionContribution::first();
        $this->assertNotNull($contribution);
        $this->assertSame($admin->id, $contribution->created_by);
        $this->assertSame($member->id, $contribution->contributor_id);

        $transaction = EditionTransaction::first();
        $this->assertNotNull($transaction);
        $this->assertSame('income', $transaction->type);
        $this->assertSame('Committee Contribution', $transaction->category);
        $this->assertSame('1000.00', $transaction->amount);
        $this->assertSame($contribution->edition_transaction_id, $transaction->id);

        // Any positive amount is accepted as an installment — no fixed
        // per-payment floor any more (Phase 3.48).
        $this->actingAs($admin)
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $member->id,
                'amount' => '50',
                'contributed_at' => '2026-02-01',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(2, EditionContribution::where('contributor_id', $member->id)->count());
    }

    public function test_inactive_committee_member_cannot_receive_a_new_contribution(): void
    {
        $edition = Edition::factory()->create();
        $inactiveMember = Contributor::factory()->create(['is_active' => false]);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $inactiveMember->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $inactiveMember->id,
                'amount' => '2000',
                'contributed_at' => '2026-01-10',
            ])
            ->assertSessionHasErrors('contributor_id');

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
        $contributorA = Contributor::factory()->create(['name' => 'Alpha Member']);
        $contributorB = Contributor::factory()->create(['name' => 'Beta Member']);

        EditionContribution::factory()->create(['edition_id' => $editionA->id, 'contributor_id' => $contributorA->id, 'amount' => '1000.00']);
        EditionContribution::factory()->create(['edition_id' => $editionA->id, 'contributor_id' => $contributorB->id, 'amount' => '2000.00']);
        EditionContribution::factory()->create(['edition_id' => $editionB->id, 'contributor_id' => $contributorA->id, 'amount' => '9000.00']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['edition_id' => $editionA->id]));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('contributions'));
        $this->assertEquals(3000.0, (float) $response->viewData('totalContributions'));

        $contributorFiltered = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['contributor_id' => $contributorA->id]));
        $this->assertCount(2, $contributorFiltered->viewData('contributions'));
    }

    // ----- Rows per page -----

    public function test_default_per_page_is_20(): void
    {
        EditionContribution::factory()->count(5)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.edition-contributions.index'));

        $response->assertViewHas('contributions', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_each_allowed_per_page_value_is_honored(): void
    {
        EditionContribution::factory()->count(5)->create();

        foreach ([10, 20, 50, 100, 200] as $value) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.edition-contributions.index', ['per_page' => $value]));

            $response->assertViewHas('contributions', fn ($paginator) => $paginator->perPage() === $value);
        }
    }

    public function test_invalid_per_page_falls_back_to_default(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['per_page' => 'lots']));

        $response->assertViewHas('contributions', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_selected_report_actions_still_work_with_a_larger_page_size(): void
    {
        $contributions = EditionContribution::factory()->count(25)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['per_page' => 100]));

        $response->assertViewHas('contributions', fn ($paginator) => $paginator->perPage() === 100 && $paginator->count() === 25);

        $selectedIds = $contributions->pluck('id')->all();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.export-selected'), ['selected_ids' => $selectedIds])
            ->assertOk();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.receipts.selected'), ['selected_ids' => $selectedIds])
            ->assertOk();
    }

    // ----- Date range -----

    public function test_date_range_filters_by_contributed_at(): void
    {
        $inRange = EditionContribution::factory()->create(['contributed_at' => '2026-03-15']);
        $before = EditionContribution::factory()->create(['contributed_at' => '2026-01-01']);
        $after = EditionContribution::factory()->create(['contributed_at' => '2026-06-01']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-contributions.index', [
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
        ]));

        $response->assertSee($inRange->receiptReference())
            ->assertDontSee($before->receiptReference())
            ->assertDontSee($after->receiptReference());
    }

    public function test_from_date_only_filters_open_ended(): void
    {
        $recent = EditionContribution::factory()->create(['contributed_at' => '2026-06-01']);
        $old = EditionContribution::factory()->create(['contributed_at' => '2026-01-01']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['from_date' => '2026-05-01']));

        $response->assertSee($recent->receiptReference())->assertDontSee($old->receiptReference());
    }

    public function test_to_date_only_filters_open_started(): void
    {
        $old = EditionContribution::factory()->create(['contributed_at' => '2026-01-01']);
        $recent = EditionContribution::factory()->create(['contributed_at' => '2026-06-01']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['to_date' => '2026-02-01']));

        $response->assertSee($old->receiptReference())->assertDontSee($recent->receiptReference());
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.edition-contributions.index', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors('to_date');
    }

    // ----- Sorting -----

    public function test_sorting_ascending_by_amount(): void
    {
        $small = EditionContribution::factory()->create(['amount' => '1000.00']);
        $large = EditionContribution::factory()->create(['amount' => '9000.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-contributions.index', [
            'sort' => 'amount', 'direction' => 'asc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, $large->receiptReference()),
            strpos($body, $small->receiptReference())
        );
    }

    public function test_sorting_descending_by_amount(): void
    {
        $small = EditionContribution::factory()->create(['amount' => '1000.00']);
        $large = EditionContribution::factory()->create(['amount' => '9000.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-contributions.index', [
            'sort' => 'amount', 'direction' => 'desc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, $small->receiptReference()),
            strpos($body, $large->receiptReference())
        );
    }

    public function test_sortable_header_renders_a_real_anchor_with_no_markdown_or_leaked_entities(): void
    {
        EditionContribution::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['sort' => 'amount', 'direction' => 'desc']))
            ->getContent();

        $this->assertMatchesRegularExpression('#<a href="[^"]*sort=amount[^"]*"[^>]*>\s*Amount\s*<span[^>]*>↓</span>\s*</a>#', $html);
        $this->assertStringNotContainsString('&darr;', $html);
        $this->assertStringNotContainsString('&amp;darr;', $html);
        $this->assertStringNotContainsString('](http', $html);
    }

    public function test_invalid_sort_column_falls_back_to_default_safely(): void
    {
        EditionContribution::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.index', ['sort' => 'notes']));

        $response->assertOk();
    }

    // ----- Selected-rows export -----

    public function test_selected_export_contains_only_the_selected_contributions(): void
    {
        $selected = EditionContribution::factory()->create();
        $notSelected = EditionContribution::factory()->create();

        $response = $this->actingAs($this->admin())->post(route('admin.edition-contributions.export-selected'), [
            'selected_ids' => [$selected->id],
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString($selected->receiptReference(), $content);
        $this->assertStringNotContainsString($notSelected->receiptReference(), $content);
    }

    public function test_selected_export_ignores_ambient_filters(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $selected = EditionContribution::factory()->create(['edition_id' => $editionTwo->id]);

        $response = $this->actingAs($this->admin())->post(
            route('admin.edition-contributions.export-selected', ['edition_id' => $editionOne->id]),
            ['selected_ids' => [$selected->id]]
        );

        $response->assertOk();
        $this->assertStringContainsString($selected->receiptReference(), $response->streamedContent());
    }

    public function test_selected_export_rejects_a_nonexistent_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.edition-contributions.export-selected'), [
            'selected_ids' => [999999],
        ]);

        $response->assertSessionHasErrors('selected_ids.0');
    }

    public function test_selected_export_requires_at_least_one_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.edition-contributions.export-selected'), [
            'selected_ids' => [],
        ]);

        $response->assertSessionHasErrors('selected_ids');
    }

    public function test_scorer_cannot_use_selected_export(): void
    {
        $contribution = EditionContribution::factory()->create();

        $this->actingAs($this->scorer())
            ->post(route('admin.edition-contributions.export-selected'), ['selected_ids' => [$contribution->id]])
            ->assertForbidden();
    }

    public function test_edition_admin_page_shows_contribution_summary_and_link(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id, 'amount' => '4000.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('4,000.00');
        $response->assertSee(route('admin.edition-contributions.index', ['edition_id' => $edition->id]), false);
    }

    // ----- Public privacy -----

    /**
     * The public edition page shows a combined contributor leaderboard
     * (name, rank, and — deliberately, per an earlier phase's
     * requirement before Phase 3.40 hid amounts — a total). This test
     * confirms names are aggregated (not repeated per row) and that
     * private fields never leak; the ranking algorithm itself is
     * covered by ContributorRankingTest.
     */
    public function test_public_edition_page_shows_aggregated_contributor_totals_without_private_data(): void
    {
        $edition = Edition::factory()->create();
        $contributorA = Contributor::factory()->create(['name' => 'Public Contributor One', 'phone' => '9876543210']);
        $contributorB = Contributor::factory()->create(['name' => 'Public Contributor Two']);

        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'contributor_id' => $contributorA->id,
            'amount' => '5432.10',
            'notes' => 'PRIVATE_NOTE_XYZ',
        ]);
        // Same contributor contributes again — name/total must appear once, combined.
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'contributor_id' => $contributorA->id,
            'amount' => '1500.00',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'contributor_id' => $contributorB->id,
            'amount' => '3000.00',
        ]);

        $response = $this->get(route('public.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Public Contributor One');
        $response->assertSee('Public Contributor Two');
        // Phase 3.40 superseded this: the public leaderboard no longer
        // renders any contribution amount at all (5432.10 + 1500.00 =
        // 6932.10 combined total is still used internally to RANK
        // contributor A first, but never shown).
        $response->assertDontSee('6,932');
        $response->assertDontSee('3,000');

        // Name appears exactly once, even though contributor A contributed twice.
        $this->assertSame(1, substr_count($response->getContent(), 'Public Contributor One'));

        $response->assertDontSee('9876543210');
        $response->assertDontSee('PRIVATE_NOTE_XYZ');
    }
}
