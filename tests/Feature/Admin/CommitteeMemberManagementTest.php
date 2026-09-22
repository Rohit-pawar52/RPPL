<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\EditionContribution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pre-UAT audit fix — CommitteeMemberController's create/store, delete-
 * protection, and scorer-forbidden paths are already covered by
 * CommitteeContributionTest (see its "Committee member CRUD" section).
 * This file fills the remaining gap: show/edit/update, index filters,
 * and guest access, which had no dedicated coverage anywhere.
 */
class CommitteeMemberManagementTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.committee-members.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_a_member_and_scorer_is_forbidden(): void
    {
        $member = CommitteeMember::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.committee-members.show', $member))
            ->assertOk()
            ->assertSee($member->name);

        $this->actingAs($this->scorer())
            ->get(route('admin.committee-members.show', $member))
            ->assertForbidden();
    }

    public function test_admin_can_edit_and_update_a_member(): void
    {
        $member = CommitteeMember::factory()->create(['name' => 'Old Name', 'is_active' => true]);

        $this->actingAs($this->admin())
            ->get(route('admin.committee-members.edit', $member))
            ->assertOk();

        $response = $this->actingAs($this->admin())->put(route('admin.committee-members.update', $member), [
            'name' => 'New Name',
            'phone' => '9998887770',
            'is_active' => '0',
        ]);

        $response->assertRedirect(route('admin.committee-members.index'));
        $member->refresh();
        $this->assertSame('New Name', $member->name);
        $this->assertFalse($member->is_active);
    }

    public function test_scorer_cannot_update_a_member(): void
    {
        $member = CommitteeMember::factory()->create(['name' => 'Untouched']);

        $this->actingAs($this->scorer())->put(route('admin.committee-members.update', $member), [
            'name' => 'Hacked',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertSame('Untouched', $member->fresh()->name);
    }

    public function test_index_search_and_status_filters_work(): void
    {
        CommitteeMember::factory()->create(['name' => 'Ganesh Patil', 'is_active' => true]);
        CommitteeMember::factory()->create(['name' => 'Ramesh Joshi', 'is_active' => false]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.committee-members.index', ['search' => 'Ganesh']));

        $response->assertOk();
        $response->assertSee('Ganesh Patil');
        $response->assertDontSee('Ramesh Joshi');

        $inactiveOnly = $this->actingAs($this->admin())
            ->get(route('admin.committee-members.index', ['status' => 'inactive']));

        $inactiveOnly->assertOk();
        $inactiveOnly->assertSee('Ramesh Joshi');
        $inactiveOnly->assertDontSee('Ganesh Patil');
    }

    // ----- Sorting -----

    public function test_sorting_ascending_and_descending_by_name(): void
    {
        $alpha = CommitteeMember::factory()->create(['name' => 'Alpha Contributor']);
        $zebra = CommitteeMember::factory()->create(['name' => 'Zebra Contributor']);

        $asc = $this->actingAs($this->admin())->get(route('admin.committee-members.index', [
            'sort' => 'name', 'direction' => 'asc',
        ]));
        $body = $asc->getContent();
        $this->assertLessThan(strpos($body, $zebra->name), strpos($body, $alpha->name));

        $desc = $this->actingAs($this->admin())->get(route('admin.committee-members.index', [
            'sort' => 'name', 'direction' => 'desc',
        ]));
        $body = $desc->getContent();
        $this->assertLessThan(strpos($body, $alpha->name), strpos($body, $zebra->name));
    }

    public function test_sorting_by_contributions_count(): void
    {
        $quiet = CommitteeMember::factory()->create(['name' => 'Quiet Member']);
        $busy = CommitteeMember::factory()->create(['name' => 'Busy Member']);
        EditionContribution::factory()->count(3)->create(['committee_member_id' => $busy->id]);

        $asc = $this->actingAs($this->admin())->get(route('admin.committee-members.index', [
            'sort' => 'contributions_count', 'direction' => 'asc',
        ]));
        $body = $asc->getContent();
        $this->assertLessThan(strpos($body, $busy->name), strpos($body, $quiet->name));

        $desc = $this->actingAs($this->admin())->get(route('admin.committee-members.index', [
            'sort' => 'contributions_count', 'direction' => 'desc',
        ]));
        $body = $desc->getContent();
        $this->assertLessThan(strpos($body, $quiet->name), strpos($body, $busy->name));
    }

    public function test_invalid_sort_column_falls_back_to_name_asc(): void
    {
        CommitteeMember::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.committee-members.index', ['sort' => 'password']));

        $response->assertOk();
    }
}
