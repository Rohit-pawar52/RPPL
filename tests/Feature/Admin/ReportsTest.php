<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\PlayerRegistration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.43 — the admin Reports hub. This is a navigation/aggregation
 * layer over already-tested exports (PlayerRegistrationController::export(),
 * EditionTransactionController::export(), EditionController::reportPdf())
 * and the Dashboard's own finance/registration/contribution semantics —
 * those are not re-verified in detail here, only that the Reports page
 * links to them correctly. The two genuinely new pieces (Contribution
 * CSV, Financial Summary) get full coverage.
 */
class ReportsTest extends TestCase
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

    // ----- Access -----

    public function test_admin_can_access_reports_page_but_scorer_and_guest_cannot(): void
    {
        Edition::factory()->create(['status' => 'active']);

        // Guest, checked first — actingAs() below would otherwise leave
        // an authenticated session active for this same test instance.
        $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));

        $this->actingAs($this->admin())->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($this->scorer())->get(route('admin.reports.index'))->assertForbidden();

        // Same authorization applies to the financial summary and the
        // contribution export — both contain admin financial data.
        $this->actingAs($this->scorer())->get(route('admin.reports.financial-summary'))->assertForbidden();
        $this->actingAs($this->scorer())->get(route('admin.edition-contributions.export'))->assertForbidden();
    }

    // ----- Edition selection/scoping -----

    /**
     * The page lists every edition in its selector dropdown (so all
     * their names legitimately appear regardless of selection) — the
     * meaningful assertion is which Edition was actually RESOLVED and
     * scoped the report links, not raw text presence.
     */
    public function test_default_and_explicit_edition_selection(): void
    {
        Edition::factory()->create(['name' => 'RPPL Completed', 'status' => 'completed', 'year' => 2023]);
        $active = Edition::factory()->create(['name' => 'RPPL Active', 'status' => 'active', 'year' => 2025]);
        $upcoming = Edition::factory()->create(['name' => 'RPPL Upcoming', 'status' => 'upcoming', 'year' => 2026]);

        // No edition_id given -> same default rule as the dashboard (active first).
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertViewHas('edition', fn ($edition) => $edition->is($active));

        // Explicit edition_id overrides the default.
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index', ['edition_id' => $upcoming->id]))
            ->assertOk()
            ->assertViewHas('edition', fn ($edition) => $edition->is($upcoming));

        // An invalid edition_id falls back to the default rather than erroring.
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index', ['edition_id' => 999999]))
            ->assertOk()
            ->assertViewHas('edition', fn ($edition) => $edition->is($active));
    }

    public function test_no_editions_shows_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('No editions available yet');
    }

    // ----- Links reuse existing exports, scoped to the selected edition -----

    public function test_reports_page_links_to_existing_reports_scoped_to_the_selected_edition(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.index', ['edition_id' => $edition->id]));

        $response->assertOk();
        $response->assertSee(e(route('admin.editions.report.pdf', $edition)), false);
        $response->assertSee(e(route('admin.player-registrations.export', ['edition_id' => $edition->id])), false);
        $response->assertSee(e(route('admin.edition-transactions.export', ['edition_id' => $edition->id])), false);
        $response->assertSee(e(route('admin.edition-contributions.export', ['edition_id' => $edition->id])), false);
        $response->assertSee(e(route('admin.reports.financial-summary', ['edition_id' => $edition->id])), false);
    }

    // ----- Contribution CSV -----

    public function test_contribution_csv_is_edition_scoped_and_contains_correct_rows(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create(['name' => 'Suresh Patil']);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $member->id,
            'amount' => 1500,
            'contributed_at' => '2026-01-15',
            'notes' => 'Cash at ground',
        ]);

        $otherEdition = Edition::factory()->create();
        $otherContributor = Contributor::factory()->create(['name' => 'Other Edition Person']);
        EditionContribution::factory()->create([
            'edition_id' => $otherEdition->id,
            'committee_member_id' => null,
            'contributor_id' => $otherContributor->id,
            'amount' => 999,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.export', ['edition_id' => $edition->id]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Suresh Patil', $csv);
        $this->assertStringContainsString('Committee Member', $csv);
        $this->assertStringContainsString('1500.00', $csv);
        $this->assertStringContainsString('2026-01-15', $csv);
        $this->assertStringContainsString('Cash at ground', $csv);

        // Never leaks another edition's contribution.
        $this->assertStringNotContainsString('Other Edition Person', $csv);
        $this->assertStringNotContainsString('999.00', $csv);

        // No phone/document data.
        $this->assertStringNotContainsString($member->phone, $csv);
    }

    // ----- Financial Summary -----

    public function test_financial_summary_matches_finance_semantics_without_double_counting(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid', 'registration_fee' => 400]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'paid', 'registration_fee' => 300]);
        PlayerRegistration::factory()->create(['edition_id' => $edition->id, 'payment_status' => 'pending', 'registration_fee' => 400]);

        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => 5000]);
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'expense', 'amount' => 1200]);

        $member = CommitteeMember::factory()->create();
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'committee_member_id' => $member->id, 'amount' => 1000]);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.financial-summary', ['edition_id' => $edition->id]));

        $response->assertOk();

        // 5000 explicit + 1000 auto-created by the contribution factory
        // (mirrors real EditionContributionService behavior) = 6000.
        $response->assertViewHas('financeSummary', function (array $summary) {
            return $summary['income'] === 6000.0 && $summary['expense'] === 1200.0 && $summary['balance'] === 4800.0;
        });

        // Paid amount is a separate operational figure, computed from
        // stored registration_fee on paid rows only — never folded into
        // the finance balance above.
        $response->assertViewHas('paidRegistrationAmount', 700.0);
        $response->assertViewHas('pendingRegistrations', 1);

        // Contribution total is shown, but the page explicitly says it's
        // already inside Finance income, not additional money.
        $response->assertViewHas('contributionTotal', 1000.0);
        $response->assertSee('already included within Finance Total Income', false);
    }

    public function test_zero_data_financial_summary_renders_cleanly(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.financial-summary', ['edition_id' => $edition->id]));

        $response->assertOk();
        $response->assertViewHas('financeSummary', ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0]);
        $response->assertViewHas('paidRegistrationAmount', 0.0);
        $response->assertViewHas('contributionTotal', 0.0);
        $response->assertViewHas('contributionCount', 0);
        $response->assertSee('₹0.00', false);
    }

    public function test_financial_summary_with_no_editions_shows_empty_state_without_error(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.financial-summary'))
            ->assertOk()
            ->assertSee('No edition available');
    }
}
