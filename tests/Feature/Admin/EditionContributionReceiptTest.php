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

class EditionContributionReceiptTest extends TestCase
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

    public function test_admin_can_view_receipt_but_scorer_is_forbidden(): void
    {
        $contribution = EditionContribution::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt', $contribution))
            ->assertOk();

        $this->actingAs($this->scorer())
            ->get(route('admin.edition-contributions.receipt', $contribution))
            ->assertForbidden();

        $this->actingAs($this->scorer())
            ->get(route('admin.edition-contributions.receipt.pdf', $contribution))
            ->assertForbidden();
    }

    public function test_receipt_shows_correct_member_edition_date_amount_and_reference(): void
    {
        $edition = Edition::factory()->create(['name' => 'RPPL 2026']);
        $member = CommitteeMember::factory()->create(['name' => 'Kavita Sharma', 'phone' => '9998887776']);
        $contribution = EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $member->id,
            'amount' => '4500.00',
            'contributed_at' => '2026-03-15',
            'notes' => 'INTERNAL_NOTE_ABC',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt', $contribution));

        $response->assertOk();
        $response->assertSee('Kavita Sharma');
        $response->assertSee('RPPL 2026');
        $response->assertSee('15 Mar 2026');
        $response->assertSee('4,500.00');
        $response->assertSee(sprintf('RPPL-CON-%06d', $contribution->id));
        $response->assertSee('Thank you for your contribution to RPPL.');
        $response->assertSee('This is a computer-generated receipt.');
    }

    public function test_receipt_never_exposes_private_or_internal_fields(): void
    {
        $creator = $this->admin();
        $member = CommitteeMember::factory()->create(['name' => 'Private Member', 'phone' => '9123456780']);
        $contribution = EditionContribution::factory()->create([
            'committee_member_id' => $member->id,
            'notes' => 'SECRET_NOTES_XYZ',
            'created_by' => $creator->id,
        ]);

        $response = $this->actingAs($creator)
            ->get(route('admin.edition-contributions.receipt', $contribution));

        $response->assertOk();
        $response->assertDontSee('9123456780');
        $response->assertDontSee('SECRET_NOTES_XYZ');
        $response->assertDontSee($creator->email);
    }

    public function test_pdf_download_returns_a_pdf_file(): void
    {
        $contribution = EditionContribution::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt.pdf', $contribution));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_nonexistent_contribution_returns_not_found(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt', ['edition_contribution' => 999999]))
            ->assertNotFound();
    }

    public function test_viewing_or_downloading_receipt_does_not_modify_contribution_data(): void
    {
        $contribution = EditionContribution::factory()->create(['amount' => '3000.00']);
        $originalUpdatedAt = $contribution->updated_at;

        $this->actingAs($this->admin())->get(route('admin.edition-contributions.receipt', $contribution));
        $this->actingAs($this->admin())->get(route('admin.edition-contributions.receipt.pdf', $contribution));

        $contribution->refresh();
        $this->assertSame('3000.00', $contribution->amount);
        $this->assertEquals($originalUpdatedAt, $contribution->updated_at);
        $this->assertSame(1, EditionTransaction::count()); // no extra ledger entry created
    }

    /**
     * Regression guard for the _receipt.blade.php extraction: the
     * single-receipt HTML preview must still render exactly the same
     * visible content it did before receipt.blade.php was rewritten to
     * include the new partial — a pure extraction, not a redesign.
     * Mirrors test_receipt_shows_correct_member_edition_date_amount_and_reference()
     * above so a regression in the shared partial fails both.
     */
    public function test_receipt_preview_still_renders_identical_content_after_partial_extraction(): void
    {
        $edition = Edition::factory()->create(['name' => 'RPPL 2026']);
        $member = CommitteeMember::factory()->create(['name' => 'Extraction Check Member']);
        $contribution = EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $member->id,
            'amount' => '2750.00',
            'contributed_at' => '2026-04-10',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt', $contribution));

        $response->assertOk();
        $response->assertSee('Extraction Check Member');
        $response->assertSee('RPPL 2026');
        $response->assertSee('10 Apr 2026');
        $response->assertSee('2,750.00');
        $response->assertSee($contribution->receiptReference());
        $response->assertSee('Contribution Receipt');
        $response->assertSee('Thank you for your contribution to RPPL.');
    }

    // ----- Bulk (selected-rows) receipts PDF -----

    public function test_receipts_selected_pdf_returns_a_combined_pdf_for_multiple_contributions(): void
    {
        $one = EditionContribution::factory()->create();
        $two = EditionContribution::factory()->create();

        $response = $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.receipts.selected'), [
                'selected_ids' => [$one->id, $two->id],
            ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_receipts_selected_pdf_requires_at_least_one_id(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.receipts.selected'), [
                'selected_ids' => [],
            ]);

        $response->assertSessionHasErrors('selected_ids');
    }

    public function test_receipts_selected_pdf_rejects_a_nonexistent_id(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.receipts.selected'), [
                'selected_ids' => [999999],
            ]);

        $response->assertSessionHasErrors('selected_ids.0');
    }

    public function test_scorer_is_forbidden_from_generating_selected_receipts_pdf(): void
    {
        $contribution = EditionContribution::factory()->create();

        $this->actingAs($this->scorer())
            ->post(route('admin.edition-contributions.receipts.selected'), [
                'selected_ids' => [$contribution->id],
            ])
            ->assertForbidden();
    }
}
