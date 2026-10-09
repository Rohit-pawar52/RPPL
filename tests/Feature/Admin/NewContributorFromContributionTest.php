<?php

namespace Tests\Feature\Admin;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Somebody who gives for the first time used to have to be added under Contributors first, then found again
 * in the contribution form. The form now takes the new person right there ("not in the list?"): the
 * contributor, the contribution, its ledger income line and the receipt come out of ONE save.
 */
class NewContributorFromContributionTest extends TestCase
{
    use RefreshDatabase;

    private int $customRoles = 0;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id]);
    }

    /**
     * A user whose custom role holds panel.access plus exactly these permissions.
     */
    private function userWith(string ...$permissions): User
    {
        $this->customRoles++;

        $role = Role::create(['name' => 'Custom '.$this->customRoles, 'slug' => 'custom-'.$this->customRoles]);
        $role->syncPermissions(['panel.access', ...$permissions]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newPerson(Edition $edition, array $overrides = []): array
    {
        return array_merge([
            'edition_id' => $edition->id,
            'new_name' => 'Rohit Pawar',
            'new_village' => 'Shirur',
            'amount' => '1500',
            'contributed_at' => '2026-03-01',
        ], $overrides);
    }

    public function test_a_first_time_giver_is_added_and_recorded_in_one_save(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, [
                'new_phone' => '9876543210',
                'new_address' => 'Near the temple, Shirur',
                'notes' => 'Cash',
            ]))
            ->assertRedirect(route('admin.edition-contributions.index'))
            ->assertSessionHas('success');

        $contributor = Contributor::where('name', 'Rohit Pawar')->firstOrFail();
        $this->assertSame('Shirur', $contributor->village);
        $this->assertSame('9876543210', $contributor->phone);
        $this->assertSame('Near the temple, Shirur', $contributor->address);
        $this->assertTrue($contributor->is_active);

        $contribution = EditionContribution::firstOrFail();
        $this->assertSame($contributor->id, $contribution->contributor_id);
        $this->assertSame('1500.00', $contribution->amount);

        // The usual by-products: the ledger income line (a general contribution) and a receipt that names the village.
        $transaction = EditionTransaction::findOrFail($contribution->edition_transaction_id);
        $this->assertSame('income', $transaction->type);
        $this->assertSame('General Contribution', $transaction->category);
        $this->assertSame('Contribution from Rohit Pawar', $transaction->description);

        $this->get(route('admin.edition-contributions.receipt', $contribution))
            ->assertOk()
            ->assertSee('Rohit Pawar, Shirur');
    }

    public function test_the_new_person_can_be_put_on_the_committee_in_the_same_save(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['add_to_committee' => '1']))
            ->assertRedirect(route('admin.edition-contributions.index'));

        $contributor = Contributor::where('name', 'Rohit Pawar')->firstOrFail();

        $this->assertTrue(EditionCommitteeMember::where('edition_id', $edition->id)->where('contributor_id', $contributor->id)->exists());
        // The membership exists before the ledger line is made, so it is already a committee contribution.
        $this->assertSame('Committee Contribution', EditionTransaction::firstOrFail()->category);
    }

    public function test_nothing_is_saved_when_the_contribution_itself_is_invalid(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['amount' => '0']))
            ->assertSessionHasErrors('amount');

        // Not even the person: no contributor is left behind without a contribution.
        $this->assertSame(0, Contributor::count());
        $this->assertSame(0, EditionContribution::count());
        $this->assertSame(0, EditionTransaction::count());
    }

    public function test_a_failure_while_saving_leaves_no_new_contributor_behind(): void
    {
        $edition = Edition::factory()->create();
        $admin = $this->admin();

        // The contributor is written first and the ledger line second: if the second step breaks, the
        // first must be undone with it (one transaction), or the next attempt would meet a stray
        // "already in the list" contributor who has never given anything.
        EditionTransaction::creating(function () {
            throw new \RuntimeException('the ledger could not be written');
        });

        try {
            app(EditionContributionService::class)->createContribution([
                'edition_id' => $edition->id,
                'new_contributor' => ['name' => 'Rohit Pawar', 'village' => 'Shirur'],
                'add_to_committee' => true,
                'amount' => '1500',
                'contributed_at' => '2026-03-01',
            ], $admin->id);

            $this->fail('The failing ledger write should have stopped the save.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('the ledger could not be written', $exception->getMessage());
        }

        $this->assertSame(0, Contributor::count());
        $this->assertSame(0, EditionCommitteeMember::count());
        $this->assertSame(0, EditionContribution::count());
    }

    public function test_saving_without_choosing_anybody_is_refused_and_the_form_never_pre_picks_a_person(): void
    {
        $edition = Edition::factory()->create();
        Contributor::factory()->create(['name' => 'Aaa First']);
        $admin = $this->admin();

        // The empty first choice is what stops a browser from selecting the first person by itself.
        $this->actingAs($admin)->get(route('admin.edition-contributions.create'))
            ->assertOk()
            ->assertSee('<option value="" selected>Select a contributor</option>', false);

        $this->post(route('admin.edition-contributions.store'), [
            'edition_id' => $edition->id,
            'contributor_id' => '',
            'amount' => '100',
            'contributed_at' => '2026-03-01',
        ])->assertSessionHasErrors('contributor_id');

        $this->assertSame(0, EditionContribution::count());
    }

    public function test_a_village_is_needed_for_somebody_new(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['new_village' => '']))
            ->assertSessionHasErrors('new_village');

        $this->assertSame(0, Contributor::count());
    }

    public function test_a_half_filled_panel_asks_for_the_name_rather_than_for_a_contributor(): void
    {
        $edition = Edition::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['new_name' => '', 'new_village' => 'Shirur']))
            ->assertSessionHasErrors('new_name')
            ->assertSessionDoesntHaveErrors('contributor_id');
    }

    public function test_picking_somebody_and_describing_somebody_new_at_once_is_refused(): void
    {
        $edition = Edition::factory()->create();
        $existing = Contributor::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['contributor_id' => $existing->id]))
            ->assertSessionHasErrors('contributor_id');

        $this->assertSame(0, EditionContribution::count());
        $this->assertSame(1, Contributor::count());
    }

    public function test_recording_for_somebody_already_in_the_list_works_exactly_as_before(): void
    {
        $edition = Edition::factory()->create();
        $existing = Contributor::factory()->create(['name' => 'Ramesh Joshi', 'village' => 'Shirur']);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), [
                'edition_id' => $edition->id,
                'contributor_id' => $existing->id,
                'amount' => '250',
                'contributed_at' => '2026-03-01',
            ])
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(1, Contributor::count());
        $this->assertSame($existing->id, EditionContribution::firstOrFail()->contributor_id);
    }

    // ----- the same person twice -----

    public function test_the_same_name_in_the_same_village_is_stopped_once_with_a_warning(): void
    {
        $edition = Edition::factory()->create();
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => 'Shirur']);
        $admin = $this->admin();

        // Capitals and extra spaces make no difference.
        $this->actingAs($admin)
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['new_name' => '  rohit   PAWAR ', 'new_village' => 'shirur']))
            ->assertSessionHasErrors('confirm_duplicate');

        $this->assertSame(1, Contributor::count());
        $this->assertSame(0, EditionContribution::count());

        // "This is a different person": now it goes through.
        $this->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['confirm_duplicate' => '1']))
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(2, Contributor::count());
        $this->assertSame(1, EditionContribution::count());
    }

    public function test_the_same_name_in_another_village_is_a_different_person_and_needs_no_warning(): void
    {
        $edition = Edition::factory()->create();
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => 'Pune']);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition))
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(2, Contributor::where('name', 'Rohit Pawar')->count());
    }

    public function test_an_older_contributor_with_no_village_counts_as_a_possible_duplicate(): void
    {
        $edition = Edition::factory()->create();
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition))
            ->assertSessionHasErrors('confirm_duplicate');
    }

    public function test_the_lookup_shows_who_is_already_there_while_the_name_is_being_typed(): void
    {
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => 'Shirur']);
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => 'Pune']);
        Contributor::factory()->create(['name' => 'Someone Else', 'village' => 'Shirur']);

        $this->actingAs($this->admin())
            ->getJson(route('admin.edition-contributions.contributor-lookup', ['name' => 'rohit pawar', 'village' => 'Shirur']))
            ->assertOk()
            ->assertJsonCount(1, 'matches')
            ->assertJsonPath('matches.0.label', 'Rohit Pawar — Shirur');
    }

    // ----- who may do this -----

    public function test_a_role_that_may_record_money_but_not_add_contributors_cannot_add_one_through_the_form(): void
    {
        $edition = Edition::factory()->create();
        $recorder = $this->userWith('finance.manage');

        $this->actingAs($recorder)
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition))
            ->assertForbidden();

        $this->assertSame(0, Contributor::count());
        $this->assertSame(0, EditionContribution::count());

        // The form does not even offer it, and still records for somebody already in the list.
        $this->get(route('admin.edition-contributions.create'))
            ->assertOk()
            ->assertDontSee('Add a new contributor');

        $existing = Contributor::factory()->create();
        $this->post(route('admin.edition-contributions.store'), [
            'edition_id' => $edition->id,
            'contributor_id' => $existing->id,
            'amount' => '100',
            'contributed_at' => '2026-03-01',
        ])->assertRedirect(route('admin.edition-contributions.index'));
    }

    public function test_putting_somebody_on_the_committee_needs_the_committee_permission(): void
    {
        $edition = Edition::factory()->create();
        $recorder = $this->userWith('finance.manage', 'contributors.manage');

        $this->actingAs($recorder)
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition, ['add_to_committee' => '1']))
            ->assertForbidden();

        $this->assertSame(0, Contributor::count());

        // Without the tick the same role may add the person.
        $this->post(route('admin.edition-contributions.store'), $this->newPerson($edition))
            ->assertRedirect(route('admin.edition-contributions.index'));

        $this->assertSame(1, Contributor::count());
        $this->assertSame(0, EditionCommitteeMember::count());
    }

    public function test_the_lookup_is_for_roles_that_may_record_contributions_only(): void
    {
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => 'Shirur']);

        $this->actingAs($this->userWith('news.manage'))
            ->getJson(route('admin.edition-contributions.contributor-lookup', ['name' => 'Rohit Pawar']))
            ->assertForbidden();
    }

    // ----- what the form shows -----

    public function test_the_form_lists_people_with_their_village_and_offers_the_new_contributor_panel(): void
    {
        Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Shirur']);
        Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Pune']);
        Contributor::factory()->create(['name' => 'Old Record', 'village' => null]);

        $this->actingAs($this->admin())
            ->get(route('admin.edition-contributions.create'))
            ->assertOk()
            ->assertSee('Ramesh Patil — Shirur')
            ->assertSee('Ramesh Patil — Pune')
            ->assertSee('Old Record')
            ->assertSee('Add a new contributor');
    }

    public function test_the_panel_reopens_with_the_warning_after_a_refused_save(): void
    {
        $edition = Edition::factory()->create();
        Contributor::factory()->create(['name' => 'Rohit Pawar', 'village' => 'Shirur']);

        $this->actingAs($this->admin())
            ->from(route('admin.edition-contributions.create'))
            ->post(route('admin.edition-contributions.store'), $this->newPerson($edition));

        $this->get(route('admin.edition-contributions.create'))
            ->assertOk()
            ->assertSee('is already a contributor')
            ->assertSee('This is a different person');
    }
}
