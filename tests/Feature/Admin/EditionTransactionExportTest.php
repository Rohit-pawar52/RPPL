<?php

namespace Tests\Feature\Admin;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionTransactionExportTest extends TestCase
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

    private function streamedCsv($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_admin_can_export_ledger_but_scorer_is_forbidden(): void
    {
        EditionTransaction::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->scorer())
            ->get(route('admin.edition-transactions.export'))
            ->assertForbidden();
    }

    public function test_csv_contains_expected_headers_and_manual_transaction_data(): void
    {
        $edition = Edition::factory()->create(['name' => 'RPPL 2026', 'year' => 2026]);
        $recorder = User::factory()->create(['role_id' => $this->adminRole->id, 'name' => 'Finance Admin']);
        $transaction = EditionTransaction::factory()->create([
            'edition_id' => $edition->id,
            'type' => 'expense',
            'category' => 'Ground fee',
            'description' => 'Stadium booking',
            'amount' => '7500.00',
            'transaction_date' => '2026-02-10',
            'created_by' => $recorder->id,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.export'));
        $response->assertOk();
        $csv = $this->streamedCsv($response);

        foreach (['Transaction ID', 'Date', 'Edition', 'Type', 'Category', 'Description', 'Amount', 'Source', 'Created By'] as $header) {
            $this->assertStringContainsString($header, $csv);
        }
        $this->assertStringContainsString((string) $transaction->id, $csv);
        $this->assertStringContainsString('2026-02-10', $csv);
        $this->assertStringContainsString('RPPL 2026', $csv);
        $this->assertStringContainsString('Expense', $csv);
        $this->assertStringContainsString('Ground fee', $csv);
        $this->assertStringContainsString('Stadium booking', $csv);
        $this->assertStringContainsString('7500.00', $csv);
        $this->assertStringContainsString('Manual', $csv);
        $this->assertStringContainsString('Finance Admin', $csv);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    /**
     * Phase 3.48: "Committee Contribution" vs "General Contribution" is
     * now computed from edition-specific committee membership (see
     * Contributor::isCommitteeMemberOf()), not a separate identity FK —
     * so this test explicitly adds the contributor to the edition's
     * committee and records the contribution through the real service,
     * the same way the admin Committee tab / contribution form would.
     */
    public function test_contribution_linked_transaction_appears_with_safe_source_label(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create(['phone' => '9998887771']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);

        $contribution = app(EditionContributionService::class)->createContribution([
            'edition_id' => $edition->id,
            'contributor_id' => $contributor->id,
            'amount' => '1000.00',
            'contributed_at' => '2026-01-10',
            'notes' => 'PRIVATE_NOTE',
        ], $this->admin()->id);
        $transaction = $contribution->transaction;

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.export'));
        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString((string) $transaction->id, $csv);
        $this->assertStringContainsString('Committee Contribution', $csv);
        // Never the contributor's private phone or contribution notes.
        $this->assertStringNotContainsString($contributor->phone, $csv);
        $this->assertStringNotContainsString('PRIVATE_NOTE', $csv);
    }

    /**
     * The same contribution-linked-transaction path, but for a
     * contributor who is NOT a committee member of the edition — the
     * ledger category must read "General Contribution", never leaking
     * which branch of the (now-internal) category logic ran.
     */
    public function test_general_contribution_linked_transaction_shows_general_category(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();

        $contribution = app(EditionContributionService::class)->createContribution([
            'edition_id' => $edition->id,
            'contributor_id' => $contributor->id,
            'amount' => '250.00',
            'contributed_at' => '2026-01-10',
        ], $this->admin()->id);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.export'));
        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString((string) $contribution->transaction->id, $csv);
        $this->assertStringContainsString('General Contribution', $csv);
    }

    public function test_edition_type_and_search_filters_are_honored(): void
    {
        $editionA = Edition::factory()->create(['year' => 2025]);
        $editionB = Edition::factory()->create(['year' => 2026]);

        $matching = EditionTransaction::factory()->create([
            'edition_id' => $editionA->id,
            'type' => 'income',
            'category' => 'Distinctive Sponsor Category',
        ]);
        EditionTransaction::factory()->create(['edition_id' => $editionB->id, 'type' => 'income', 'category' => 'Other Edition']);
        EditionTransaction::factory()->create(['edition_id' => $editionA->id, 'type' => 'expense', 'category' => 'Other Type']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.export', [
            'edition_id' => $editionA->id,
            'type' => 'income',
        ]));
        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString('Distinctive Sponsor Category', $csv);
        $this->assertStringNotContainsString('Other Edition', $csv);
        $this->assertStringNotContainsString('Other Type', $csv);
        $this->assertStringContainsString('rppl-finance-2025.csv', $response->headers->get('content-disposition'));

        $searchResponse = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.export', ['search' => 'Distinctive Sponsor Category']));
        $searchCsv = $this->streamedCsv($searchResponse);
        $this->assertStringContainsString($matching->description ?? 'Distinctive Sponsor Category', $searchCsv);
    }

    public function test_empty_result_returns_valid_header_only_csv_without_totals(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.export', ['search' => 'Nothing Matches Anything']));

        $response->assertOk();
        $csv = $this->streamedCsv($response);

        $this->assertStringContainsString('Transaction ID', $csv);
        $this->assertSame(1, count(array_filter(explode("\n", $csv))));
        $this->assertStringNotContainsString('Total Income', $csv);
        $this->assertStringNotContainsString('Balance', $csv);
    }
}
