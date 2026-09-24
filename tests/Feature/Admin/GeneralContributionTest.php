<?php

namespace Tests\Feature\Admin;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recording a contribution from a Contributor who is NOT on the
 * selected edition's committee — the "General Contribution" path
 * through the single, Phase-3.48-simplified contribution form. Committee
 * contribution/dues behavior is covered by CommitteeContributionTest.
 */
class GeneralContributionTest extends TestCase
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

    public function test_admin_can_create_a_general_contribution_creating_a_linked_income_transaction(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create(['name' => 'Ramesh Joshi']);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $contributor->id,
                'amount' => '250',
                'contributed_at' => '2026-03-01',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $contribution = EditionContribution::first();
        $this->assertNotNull($contribution);
        $this->assertSame($contributor->id, $contribution->contributor_id);

        $transaction = $contribution->transaction;
        $this->assertSame('income', $transaction->type);
        $this->assertSame('General Contribution', $transaction->category);
        $this->assertSame('250.00', $transaction->amount);
    }

    public function test_general_contribution_accepts_any_genuinely_positive_amount(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $contributor->id,
                'amount' => '50',
                'contributed_at' => '2026-03-01',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(1, EditionContribution::count());
    }

    public function test_zero_or_negative_amount_is_rejected(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();
        $admin = $this->admin();

        foreach (['0', '-5'] as $badAmount) {
            $this->actingAs($admin)
                ->post(route('admin.edition-contributions.store'), [
                    'edition_id' => $edition->id,
                    'contributor_id' => $contributor->id,
                    'amount' => $badAmount,
                    'contributed_at' => '2026-03-01',
                ])
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, EditionContribution::count());
    }

    public function test_inactive_contributor_cannot_receive_a_new_contribution(): void
    {
        $edition = Edition::factory()->create();
        $inactive = Contributor::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $inactive->id,
                'amount' => '500',
                'contributed_at' => '2026-03-01',
            ])
            ->assertSessionHasErrors('contributor_id');

        $this->assertSame(0, EditionContribution::count());
    }

    public function test_receipt_works_for_a_general_contributor_and_shows_their_name(): void
    {
        $contributor = Contributor::factory()->create(['name' => 'Anjali Verma']);
        $contribution = EditionContribution::factory()->create([
            'contributor_id' => $contributor->id,
            'amount' => '750.00',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt', $contribution));

        $response->assertOk();
        $response->assertSee('Anjali Verma');
        $response->assertSee(sprintf('RPPL-CON-%06d', $contribution->id));

        $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.receipt.pdf', $contribution))
            ->assertOk();
    }

    public function test_deleting_a_general_contribution_atomically_deletes_its_linked_transaction(): void
    {
        $contributor = Contributor::factory()->create();
        $contribution = EditionContribution::factory()->create(['contributor_id' => $contributor->id]);
        $transactionId = $contribution->edition_transaction_id;

        $this->actingAs($this->admin())
            ->delete(route('admin.edition-contributions.destroy', $contribution))
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertDatabaseMissing('edition_contributions', ['id' => $contribution->id]);
        $this->assertDatabaseMissing('edition_transactions', ['id' => $transactionId]);
    }

    public function test_contributor_with_contribution_history_cannot_be_deleted(): void
    {
        $withHistory = Contributor::factory()->create();
        EditionContribution::factory()->create(['contributor_id' => $withHistory->id]);
        $withoutHistory = Contributor::factory()->create();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete(route('admin.contributors.destroy', $withHistory))
            ->assertRedirect(route('admin.contributors.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('contributors', ['id' => $withHistory->id]);

        $this->actingAs($admin)
            ->delete(route('admin.contributors.destroy', $withoutHistory))
            ->assertRedirect(route('admin.contributors.index'));
        $this->assertDatabaseMissing('contributors', ['id' => $withoutHistory->id]);
    }

    public function test_transaction_linked_to_a_general_contribution_still_cannot_be_directly_edited_or_deleted(): void
    {
        $contributor = Contributor::factory()->create();
        $contribution = EditionContribution::factory()->create(['contributor_id' => $contributor->id]);
        $transaction = $contribution->transaction;

        $this->actingAs($this->admin())
            ->delete(route('admin.edition-transactions.destroy', $transaction))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('edition_transactions', ['id' => $transaction->id]);
    }

    public function test_scorer_is_forbidden_from_recording_a_general_contribution(): void
    {
        $edition = Edition::factory()->create();
        $contributor = Contributor::factory()->create();

        $this->actingAs($this->scorer())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $contributor->id,
                'amount' => '500',
                'contributed_at' => '2026-03-01',
            ])
            ->assertForbidden();

        $this->assertSame(0, EditionContribution::count());
    }
}
