<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.48 — CommitteeMemberController and its CRUD views were
 * removed (committee membership is now edition-specific, managed from
 * the Finance "Committee" tab — see EditionCommitteeMemberController /
 * CommitteeContributionTest). This file covers only what remains: the
 * old admin.committee-members.* routes as compatibility redirects, so
 * an old bookmark/link never 404s.
 */
class CommitteeMemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.committee-members.index'))->assertRedirect(route('admin.login'));
    }

    public function test_index_redirects_to_the_finance_committee_tab(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.committee-members.index'))
            ->assertRedirect(route('admin.finance.committee'));
    }

    public function test_show_redirects_to_the_linked_contributor_when_one_exists(): void
    {
        $member = CommitteeMember::factory()->create();
        $contributor = Contributor::factory()->create(['committee_member_id' => $member->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.committee-members.show', $member))
            ->assertRedirect(route('admin.contributors.show', $contributor));
    }

    public function test_show_falls_back_to_the_finance_committee_tab_when_no_contributor_is_linked(): void
    {
        $member = CommitteeMember::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.committee-members.show', $member))
            ->assertRedirect(route('admin.finance.committee'));
    }
}
