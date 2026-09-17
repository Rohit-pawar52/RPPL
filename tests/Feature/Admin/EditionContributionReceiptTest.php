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
}
