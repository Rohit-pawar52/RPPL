<?php

namespace Tests\Feature\Admin;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A contributor is told apart from a namesake by village (and an optional address): required when somebody
 * new is added, optional on an older record, and shown wherever people are listed, searched or exported.
 */
class ContributorVillageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id]);
    }

    public function test_a_new_contributor_needs_a_village_and_gets_tidy_values(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.contributors.store'), ['name' => 'Ganesh Patil'])
            ->assertSessionHasErrors('village');

        $this->post(route('admin.contributors.store'), [
            'name' => '  Ganesh   Patil ',
            'village' => '  Shirur ',
            'phone' => '',
            'address' => '  Near   the temple ',
        ])->assertRedirect(route('admin.contributors.index'));

        $contributor = Contributor::firstOrFail();
        $this->assertSame('Ganesh Patil', $contributor->name);
        $this->assertSame('Shirur', $contributor->village);
        $this->assertSame('Near the temple', $contributor->address);
        $this->assertNull($contributor->phone);
    }

    public function test_the_same_person_cannot_be_added_twice_by_accident(): void
    {
        Contributor::factory()->create(['name' => 'Ganesh Patil', 'village' => 'Shirur']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.contributors.store'), ['name' => 'ganesh patil', 'village' => 'SHIRUR'])
            ->assertSessionHasErrors('confirm_duplicate');
        $this->assertSame(1, Contributor::count());

        $this->post(route('admin.contributors.store'), ['name' => 'ganesh patil', 'village' => 'SHIRUR', 'confirm_duplicate' => '1'])
            ->assertRedirect(route('admin.contributors.index'));
        $this->assertSame(2, Contributor::count());

        // The confirmation is not a column: nothing odd was stored.
        $this->assertArrayNotHasKey('confirm_duplicate', Contributor::latest('id')->first()->getAttributes());
    }

    public function test_an_older_contributor_without_a_village_can_still_be_edited_and_given_one_later(): void
    {
        $admin = $this->admin();
        $old = Contributor::factory()->create(['name' => 'Old Record', 'village' => null]);

        $this->actingAs($admin)
            ->put(route('admin.contributors.update', $old), ['name' => 'Old Record', 'village' => '', 'is_active' => '1'])
            ->assertRedirect(route('admin.contributors.index'));
        $this->assertNull($old->fresh()->village);

        $this->put(route('admin.contributors.update', $old), ['name' => 'Old Record', 'village' => 'Pune', 'address' => 'Camp', 'is_active' => '1'])
            ->assertRedirect(route('admin.contributors.index'));

        $this->assertSame('Pune', $old->fresh()->village);
        $this->assertSame('Camp', $old->fresh()->address);
    }

    public function test_the_list_shows_and_searches_by_village_and_phone(): void
    {
        Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Shirur', 'phone' => '9000000001']);
        Contributor::factory()->create(['name' => 'Suresh Kale', 'village' => 'Pune', 'phone' => '9000000002']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.contributors.index'))->assertOk()->assertSee('Shirur')->assertSee('Pune');

        $this->get(route('admin.contributors.index', ['search' => 'shirur']))
            ->assertSee('Ramesh Patil')->assertDontSee('Suresh Kale');
        $this->get(route('admin.contributors.index', ['search' => '9000000002']))
            ->assertSee('Suresh Kale')->assertDontSee('Ramesh Patil');
        $this->get(route('admin.contributors.index', ['sort' => 'village']))->assertOk();
    }

    public function test_the_profile_the_dues_table_and_the_committee_dropdown_show_the_village(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $member = Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Shirur', 'address' => 'Near the temple']);
        $other = Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Pune']);
        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $member->id]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.contributors.show', $member))
            ->assertOk()->assertSee('Shirur')->assertSee('Near the temple');
        $this->get(route('admin.finance.overview', ['edition_id' => $edition->id]))->assertOk()->assertSee('Shirur');
        $this->get(route('admin.finance.committee', ['edition_id' => $edition->id]))
            ->assertOk()->assertSee('Shirur')->assertSee('Ramesh Patil — Pune');   // the one not on the committee yet
        $this->assertNotNull($other->id);
    }

    public function test_exports_lists_and_filters_tell_namesakes_apart(): void
    {
        $edition = Edition::factory()->create();
        $admin = $this->admin();
        $a = Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Shirur']);
        $b = Contributor::factory()->create(['name' => 'Ramesh Patil', 'village' => 'Pune']);

        foreach ([$a, $b] as $i => $contributor) {
            app(EditionContributionService::class)->createContribution([
                'edition_id' => $edition->id, 'contributor_id' => $contributor->id,
                'amount' => 100 * ($i + 1), 'contributed_at' => '2026-03-01',
            ], $admin->id);
        }

        $this->actingAs($admin);

        // list: both namesakes with their villages; the filter dropdown labels them; search by village narrows.
        $this->get(route('admin.edition-contributions.index'))->assertOk()
            ->assertSee('Ramesh Patil — Shirur')->assertSee('Ramesh Patil — Pune');
        $this->get(route('admin.edition-contributions.index', ['search' => 'Pune']))->assertOk()->assertSee('₹200')->assertDontSee('₹100');

        // CSV: the new column is last, so existing columns keep their place.
        $csv = $this->get(route('admin.edition-contributions.export'))->streamedContent();
        $this->assertStringContainsString('"Transaction ID","Contributor Village"', $csv);
        $this->assertStringContainsString('Shirur', $csv);
        $this->assertStringContainsString('Pune', $csv);
    }
}
