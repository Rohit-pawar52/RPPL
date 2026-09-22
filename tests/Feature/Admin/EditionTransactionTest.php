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

    // ----- Rows per page -----

    public function test_default_per_page_is_20(): void
    {
        EditionTransaction::factory()->count(5)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index'));

        $response->assertViewHas('transactions', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_each_allowed_per_page_value_is_honored(): void
    {
        EditionTransaction::factory()->count(5)->create();

        foreach ([10, 20, 50, 100, 200] as $value) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.edition-transactions.index', ['per_page' => $value]));

            $response->assertViewHas('transactions', fn ($paginator) => $paginator->perPage() === $value);
        }
    }

    public function test_invalid_per_page_falls_back_to_default(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['per_page' => 'lots']));

        $response->assertViewHas('transactions', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_per_page_survives_filter_and_sort(): void
    {
        $edition = Edition::factory()->create();
        EditionTransaction::factory()->count(5)->create(['edition_id' => $edition->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index', [
            'edition_id' => $edition->id,
            'sort' => 'amount',
            'direction' => 'asc',
            'per_page' => 50,
        ]));

        $response->assertOk();
        $response->assertViewHas('transactions', fn ($paginator) => $paginator->perPage() === 50);
    }

    public function test_selected_export_still_works_with_a_larger_page_size(): void
    {
        $transactions = EditionTransaction::factory()->count(25)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['per_page' => 100]));

        $response->assertViewHas('transactions', fn ($paginator) => $paginator->perPage() === 100 && $paginator->count() === 25);

        $exportResponse = $this->actingAs($this->admin())->post(route('admin.edition-transactions.export-selected'), [
            'selected_ids' => $transactions->pluck('id')->all(),
        ]);

        $exportResponse->assertOk();
    }

    // ----- Date range -----

    public function test_date_range_filters_by_transaction_date(): void
    {
        $inRange = EditionTransaction::factory()->create(['category' => 'InRangeCategory', 'transaction_date' => '2026-03-15']);
        $before = EditionTransaction::factory()->create(['category' => 'BeforeCategory', 'transaction_date' => '2026-01-01']);
        $after = EditionTransaction::factory()->create(['category' => 'AfterCategory', 'transaction_date' => '2026-06-01']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index', [
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
        ]));

        $response->assertSee($inRange->category)
            ->assertDontSee($before->category)
            ->assertDontSee($after->category);
    }

    public function test_from_date_only_filters_open_ended(): void
    {
        $recent = EditionTransaction::factory()->create(['category' => 'RecentCategory', 'transaction_date' => '2026-06-01']);
        $old = EditionTransaction::factory()->create(['category' => 'OldCategory', 'transaction_date' => '2026-01-01']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['from_date' => '2026-05-01']));

        $response->assertSee($recent->category)->assertDontSee($old->category);
    }

    public function test_to_date_only_filters_open_started(): void
    {
        $old = EditionTransaction::factory()->create(['category' => 'OldCategory', 'transaction_date' => '2026-01-01']);
        $recent = EditionTransaction::factory()->create(['category' => 'RecentCategory', 'transaction_date' => '2026-06-01']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['to_date' => '2026-02-01']));

        $response->assertSee($old->category)->assertDontSee($recent->category);
    }

    public function test_to_date_before_from_date_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors('to_date');
    }

    // ----- Sorting -----

    public function test_sorting_ascending_by_amount(): void
    {
        $cheap = EditionTransaction::factory()->create(['category' => 'CheapCategory', 'amount' => '100.00']);
        $expensive = EditionTransaction::factory()->create(['category' => 'ExpensiveCategory', 'amount' => '900.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index', [
            'sort' => 'amount', 'direction' => 'asc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, $expensive->category),
            strpos($body, $cheap->category)
        );
    }

    public function test_sorting_descending_by_amount(): void
    {
        $cheap = EditionTransaction::factory()->create(['category' => 'CheapCategory', 'amount' => '100.00']);
        $expensive = EditionTransaction::factory()->create(['category' => 'ExpensiveCategory', 'amount' => '900.00']);

        $response = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index', [
            'sort' => 'amount', 'direction' => 'desc',
        ]));

        $body = $response->getContent();
        $this->assertLessThan(
            strpos($body, $cheap->category),
            strpos($body, $expensive->category)
        );
    }

    public function test_invalid_sort_column_falls_back_to_default_safely(): void
    {
        EditionTransaction::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['sort' => 'created_by']));

        $response->assertOk();
    }

    // ----- Sortable header rendering (regression) -----
    //
    // A prior bug: the shared <x-sortable-header> component wrote its
    // arrow as the HTML entity string '&uarr;'/'&darr;' inside a Blade
    // {{ }} expression, which auto-escapes via e() — so the entity got
    // double-escaped into literal on-page text ("&amp;darr;", visible as
    // "&darr;") instead of an arrow glyph. These tests inspect the
    // actual rendered HTML (not just DB ordering) so that regression,
    // or any variant of it, can never land unnoticed again.

    public function test_sortable_header_renders_as_a_real_anchor_tag(): void
    {
        EditionTransaction::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index'))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*sort=amount[^"]*"[^>]*>\s*Amount\s*</a>#',
            $html
        );
    }

    public function test_active_sortable_header_shows_label_and_a_real_arrow_glyph(): void
    {
        EditionTransaction::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['sort' => 'amount', 'direction' => 'desc']))
            ->getContent();

        // The real ↓ glyph must appear...
        $this->assertStringContainsString('↓', $html);
        // ...and never as an escaped/unescaped HTML entity string.
        $this->assertStringNotContainsString('&darr;', $html);
        $this->assertStringNotContainsString('&amp;darr;', $html);
        $this->assertStringNotContainsString('&uarr;', $html);
        $this->assertStringNotContainsString('&amp;uarr;', $html);
    }

    public function test_sortable_header_never_renders_markdown_link_syntax(): void
    {
        EditionTransaction::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['sort' => 'amount', 'direction' => 'desc']))
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/\[Amount[^\]]*\]\(https?:\/\/[^)]*\)/', $html);
        $this->assertStringNotContainsString('](http', $html);
    }

    public function test_sortable_header_toggle_url_switches_direction_and_preserves_filters(): void
    {
        $edition = Edition::factory()->create();
        EditionTransaction::factory()->create(['edition_id' => $edition->id]);

        $html = $this->actingAs($this->admin())->get(route('admin.edition-transactions.index', [
            'edition_id' => $edition->id,
            'sort' => 'amount',
            'direction' => 'asc',
        ]))->getContent();

        preg_match('#<a href="([^"]*sort=amount[^"]*)"[^>]*>\s*Amount#', $html, $matches);
        $this->assertNotEmpty($matches, 'Expected to find the Amount sortable header link in the response.');

        $toggleUrl = html_entity_decode($matches[1]);
        $this->assertStringContainsString('direction=desc', $toggleUrl);
        $this->assertStringContainsString('edition_id='.$edition->id, $toggleUrl);
    }

    public function test_switching_to_a_different_sort_column_uses_its_own_default_direction(): void
    {
        EditionTransaction::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.edition-transactions.index', ['sort' => 'amount', 'direction' => 'asc']))
            ->getContent();

        // The Type header is currently inactive (sort=amount is active),
        // so clicking it must propose 'asc' (its own default first click),
        // not whatever direction the currently-active column happens to be in.
        preg_match('#<a href="([^"]*sort=type[^"]*)"[^>]*>\s*Type#', $html, $matches);
        $this->assertNotEmpty($matches, 'Expected to find the Type sortable header link in the response.');
        $this->assertStringContainsString('direction=asc', html_entity_decode($matches[1]));
    }

    // ----- Selected-rows export -----

    public function test_selected_export_contains_only_the_selected_transactions(): void
    {
        $selected = EditionTransaction::factory()->create(['category' => 'SelectedCategory']);
        $notSelected = EditionTransaction::factory()->create(['category' => 'NotSelectedCategory']);

        $response = $this->actingAs($this->admin())->post(route('admin.edition-transactions.export-selected'), [
            'selected_ids' => [$selected->id],
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString($selected->category, $content);
        $this->assertStringNotContainsString($notSelected->category, $content);
    }

    public function test_selected_export_ignores_ambient_filters(): void
    {
        $editionOne = Edition::factory()->create();
        $editionTwo = Edition::factory()->create();
        $selected = EditionTransaction::factory()->create(['edition_id' => $editionTwo->id, 'category' => 'IgnoresFiltersCategory']);

        $response = $this->actingAs($this->admin())->post(
            route('admin.edition-transactions.export-selected', ['edition_id' => $editionOne->id]),
            ['selected_ids' => [$selected->id]]
        );

        $response->assertOk();
        $this->assertStringContainsString($selected->category, $response->streamedContent());
    }

    public function test_selected_export_rejects_a_nonexistent_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.edition-transactions.export-selected'), [
            'selected_ids' => [999999],
        ]);

        $response->assertSessionHasErrors('selected_ids.0');
    }

    public function test_selected_export_requires_at_least_one_id(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.edition-transactions.export-selected'), [
            'selected_ids' => [],
        ]);

        $response->assertSessionHasErrors('selected_ids');
    }

    public function test_scorer_cannot_use_selected_export(): void
    {
        $transaction = EditionTransaction::factory()->create();

        $this->actingAs($this->scorer())
            ->post(route('admin.edition-transactions.export-selected'), ['selected_ids' => [$transaction->id]])
            ->assertForbidden();
    }
}
