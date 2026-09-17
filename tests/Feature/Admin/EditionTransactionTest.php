<?php

namespace Tests\Feature\Admin;

use App\Models\Edition;
use App\Models\EditionTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionTransactionTest extends TestCase
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

    public function test_admin_can_view_ledger_but_scorer_is_forbidden_from_all_crud_endpoints(): void
    {
        $edition = Edition::factory()->create();
        $transaction = EditionTransaction::factory()->create(['edition_id' => $edition->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index'))
            ->assertOk();

        // Valid payloads, so a 403 can only mean the policy check itself
        // blocked the request rather than validation failing first.
        $validPayload = [
            'edition_id' => $edition->id,
            'type' => 'income',
            'amount' => '100.00',
            'transaction_date' => '2026-01-01',
        ];

        $scorer = $this->scorer();
        $this->actingAs($scorer)->get(route('admin.edition-transactions.index'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.edition-transactions.create'))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.edition-transactions.show', $transaction))->assertForbidden();
        $this->actingAs($scorer)->get(route('admin.edition-transactions.edit', $transaction))->assertForbidden();
        $this->actingAs($scorer)->post(route('admin.edition-transactions.store'), $validPayload)->assertForbidden();
        $this->actingAs($scorer)->put(route('admin.edition-transactions.update', $transaction), $validPayload)->assertForbidden();
        $this->actingAs($scorer)->delete(route('admin.edition-transactions.destroy', $transaction))->assertForbidden();

        $this->assertDatabaseCount('edition_transactions', 1); // only the original fixture — scorer created nothing
    }

    public function test_admin_can_create_income_and_expense_with_created_by_from_auth_not_request(): void
    {
        $edition = Edition::factory()->create();
        $admin = $this->admin();
        $impersonatedUserId = User::factory()->create(['role_id' => $this->adminRole->id])->id;

        $this->actingAs($admin)
            ->post(route('admin.edition-transactions.store'), [
                'edition_id' => $edition->id,
                'type' => 'income',
                'category' => 'Sponsorship',
                'amount' => '15000.50',
                'transaction_date' => '2026-01-15',
                'description' => 'Title sponsor payment',
                'created_by' => $impersonatedUserId, // must be ignored
            ])
            ->assertRedirect(route('admin.edition-transactions.index'));

        $income = EditionTransaction::where('type', 'income')->first();
        $this->assertNotNull($income);
        $this->assertSame($admin->id, $income->created_by);
        $this->assertNotSame($impersonatedUserId, $income->created_by);
        $this->assertSame('15000.50', $income->amount);

        $this->actingAs($admin)
            ->post(route('admin.edition-transactions.store'), [
                'edition_id' => $edition->id,
                'type' => 'expense',
                'category' => 'Ground fee',
                'amount' => '5000',
                'transaction_date' => '2026-01-20',
            ])
            ->assertRedirect(route('admin.edition-transactions.index'));

        $this->assertSame(1, EditionTransaction::where('type', 'expense')->count());
    }

    public function test_validation_rejects_invalid_type_and_non_positive_amount(): void
    {
        $edition = Edition::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.edition-transactions.store'), [
                'edition_id' => $edition->id,
                'type' => 'refund', // not a valid TYPES value
                'amount' => '100',
                'transaction_date' => '2026-01-01',
            ])
            ->assertSessionHasErrors('type');

        $this->actingAs($admin)
            ->post(route('admin.edition-transactions.store'), [
                'edition_id' => $edition->id,
                'type' => 'income',
                'amount' => '0',
                'transaction_date' => '2026-01-01',
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($admin)
            ->post(route('admin.edition-transactions.store'), [
                'edition_id' => $edition->id,
                'type' => 'income',
                'amount' => '-50',
                'transaction_date' => '2026-01-01',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, EditionTransaction::count());
    }

    public function test_update_works_but_created_by_remains_immutable(): void
    {
        $edition = Edition::factory()->create();
        $originalCreator = $this->admin();
        $transaction = EditionTransaction::factory()->create([
            'edition_id' => $edition->id,
            'type' => 'income',
            'amount' => '100.00',
            'created_by' => $originalCreator->id,
        ]);

        $anotherAdmin = $this->admin();
        $impersonatedUserId = User::factory()->create(['role_id' => $this->adminRole->id])->id;

        $this->actingAs($anotherAdmin)
            ->put(route('admin.edition-transactions.update', $transaction), [
                'edition_id' => $edition->id,
                'type' => 'expense',
                'category' => 'Updated category',
                'amount' => '250.75',
                'transaction_date' => '2026-02-01',
                'created_by' => $impersonatedUserId, // must be ignored
            ])
            ->assertRedirect(route('admin.edition-transactions.index'));

        $transaction->refresh();
        $this->assertSame('expense', $transaction->type);
        $this->assertSame('Updated category', $transaction->category);
        $this->assertSame('250.75', $transaction->amount);
        $this->assertSame($originalCreator->id, $transaction->created_by); // unchanged
    }

    public function test_admin_can_delete_a_transaction(): void
    {
        $transaction = EditionTransaction::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.edition-transactions.destroy', $transaction))
            ->assertRedirect(route('admin.edition-transactions.index'));

        $this->assertDatabaseMissing('edition_transactions', ['id' => $transaction->id]);
    }

    public function test_edition_and_type_filters_and_summary_totals_are_correct(): void
    {
        $editionA = Edition::factory()->create();
        $editionB = Edition::factory()->create();

        EditionTransaction::factory()->create(['edition_id' => $editionA->id, 'type' => 'income', 'amount' => '1000.00']);
        EditionTransaction::factory()->create(['edition_id' => $editionA->id, 'type' => 'income', 'amount' => '500.00']);
        EditionTransaction::factory()->create(['edition_id' => $editionA->id, 'type' => 'expense', 'amount' => '300.00']);
        // Different edition — must not affect edition A's summary/filtered list.
        EditionTransaction::factory()->create(['edition_id' => $editionB->id, 'type' => 'income', 'amount' => '9999.00']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['edition_id' => $editionA->id]));

        $response->assertOk();
        $response->assertViewHas('summary', ['income' => 1500.0, 'expense' => 300.0, 'balance' => 1200.0]);
        $this->assertCount(3, $response->viewData('transactions'));

        $incomeOnly = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['edition_id' => $editionA->id, 'type' => 'income']));
        $this->assertCount(2, $incomeOnly->viewData('transactions'));
    }

    public function test_edition_show_page_displays_finance_summary_and_link_to_filtered_ledger(): void
    {
        $edition = Edition::factory()->create();
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'income', 'amount' => '2000.00']);
        EditionTransaction::factory()->create(['edition_id' => $edition->id, 'type' => 'expense', 'amount' => '750.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('2,000.00');
        $response->assertSee('750.00');
        $response->assertSee('1,250.00'); // balance
        $response->assertSee(route('admin.edition-transactions.index', ['edition_id' => $edition->id]), false);
    }
}
