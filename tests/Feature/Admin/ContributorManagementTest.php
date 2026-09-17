<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\EditionContribution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.38B1 — master-data layer only for general RPPL contributors.
 * No contribution recording exists yet; these tests cover identity CRUD,
 * the explicit committee-member link and its uniqueness enforcement, and
 * authorization, mirroring CommitteeMemberController's own proven test
 * shape (see CommitteeContributionTest).
 */
class ContributorManagementTest extends TestCase
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

    public function test_admin_can_access_contributor_index_and_create_a_contributor_but_scorer_is_forbidden(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.contributors.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.contributors.create'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.contributors.store'), ['name' => 'Ganesh Patil', 'phone' => '9876500002'])
            ->assertRedirect(route('admin.contributors.index'));

        $contributor = Contributor::firstWhere('name', 'Ganesh Patil');
        $this->assertNotNull($contributor);
        $this->assertTrue($contributor->is_active);
        $this->assertNull($contributor->committee_member_id);

        $scorer = $this->scorer();
        $this->actingAs($scorer)->get(route('admin.contributors.index'))->assertForbidden();
        $this->actingAs($scorer)
            ->post(route('admin.contributors.store'), ['name' => 'Blocked'])
            ->assertForbidden();
        $this->assertNull(Contributor::firstWhere('name', 'Blocked'));
    }

    public function test_a_contributor_can_be_optionally_linked_to_a_committee_member(): void
    {
        $member = CommitteeMember::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.contributors.store'), [
                'name' => 'Suresh Deshmukh',
                'committee_member_id' => $member->id,
            ])
            ->assertRedirect(route('admin.contributors.index'));

        $contributor = Contributor::firstWhere('name', 'Suresh Deshmukh');
        $this->assertSame($member->id, $contributor->committee_member_id);
        $this->assertTrue($member->fresh()->contributor->is($contributor));
    }

    public function test_the_same_committee_member_cannot_be_linked_to_two_contributors(): void
    {
        $member = CommitteeMember::factory()->create();
        Contributor::factory()->create(['committee_member_id' => $member->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.contributors.store'), [
                'name' => 'Second Link Attempt',
                'committee_member_id' => $member->id,
            ])
            ->assertSessionHasErrors('committee_member_id');

        $this->assertNull(Contributor::firstWhere('name', 'Second Link Attempt'));
    }

    public function test_update_can_change_the_committee_link_and_can_keep_its_own_existing_link(): void
    {
        $memberA = CommitteeMember::factory()->create();
        $memberB = CommitteeMember::factory()->create();
        $contributor = Contributor::factory()->create(['committee_member_id' => $memberA->id]);

        // Re-saving with its OWN current link must not trip the
        // uniqueness rule against itself.
        $this->actingAs($this->admin())
            ->put(route('admin.contributors.update', $contributor), [
                'name' => $contributor->name,
                'committee_member_id' => $memberA->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.contributors.index'));
        $this->assertSame($memberA->id, $contributor->fresh()->committee_member_id);

        // Changing to a different, unlinked member succeeds.
        $this->actingAs($this->admin())
            ->put(route('admin.contributors.update', $contributor), [
                'name' => $contributor->name,
                'committee_member_id' => $memberB->id,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.contributors.index'));
        $this->assertSame($memberB->id, $contributor->fresh()->committee_member_id);
    }

    public function test_inactive_status_can_be_stored_via_update(): void
    {
        $contributor = Contributor::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())
            ->put(route('admin.contributors.update', $contributor), [
                'name' => $contributor->name,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.contributors.index'));

        $this->assertFalse($contributor->fresh()->is_active);
    }

    public function test_deleting_a_contributor_does_not_delete_its_linked_committee_member(): void
    {
        $member = CommitteeMember::factory()->create();
        $contributor = Contributor::factory()->create(['committee_member_id' => $member->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.contributors.destroy', $contributor))
            ->assertRedirect(route('admin.contributors.index'));

        $this->assertDatabaseMissing('contributors', ['id' => $contributor->id]);
        $this->assertDatabaseHas('committee_members', ['id' => $member->id]);
    }

    // ----- Photo lifecycle (Phase 3.40) -----

    /**
     * ->create() (not ->image()): ->image() requires the GD extension
     * purely to render real pixel data, which this environment doesn't
     * have — ->create() with an explicit MIME type satisfies the
     * image/mimes validation rules identically without it.
     */
    private function fakePhoto(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 200, 'image/jpeg');
    }

    public function test_admin_can_create_a_contributor_with_a_photo_stored_on_the_public_disk(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.contributors.store'), [
                'name' => 'Photo Contributor',
                'photo' => $this->fakePhoto(),
            ])
            ->assertRedirect(route('admin.contributors.index'));

        $contributor = Contributor::firstWhere('name', 'Photo Contributor');
        $this->assertNotNull($contributor->photo_path);
        $this->assertStringStartsWith('contributors/', $contributor->photo_path);
        Storage::disk('public')->assertExists($contributor->photo_path);
    }

    public function test_admin_can_create_a_contributor_without_a_photo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.contributors.store'), ['name' => 'No Photo Contributor'])
            ->assertRedirect(route('admin.contributors.index'));

        $contributor = Contributor::firstWhere('name', 'No Photo Contributor');
        $this->assertNull($contributor->photo_path);
    }

    public function test_admin_can_replace_a_contributor_photo_and_the_old_file_is_removed(): void
    {
        Storage::fake('public');
        $originalPath = $this->fakePhoto('original.jpg')->store('contributors', 'public');
        $contributor = Contributor::factory()->create(['photo_path' => $originalPath]);

        $this->actingAs($this->admin())
            ->put(route('admin.contributors.update', $contributor), [
                'name' => $contributor->name,
                'is_active' => 1,
                'photo' => $this->fakePhoto('replacement.jpg'),
            ])
            ->assertRedirect(route('admin.contributors.index'));

        $contributor->refresh();
        $this->assertNotSame($originalPath, $contributor->photo_path);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($contributor->photo_path);
    }

    public function test_update_without_a_new_photo_keeps_the_existing_one(): void
    {
        Storage::fake('public');
        $originalPath = $this->fakePhoto()->store('contributors', 'public');
        $contributor = Contributor::factory()->create(['photo_path' => $originalPath]);

        $this->actingAs($this->admin())
            ->put(route('admin.contributors.update', $contributor), [
                'name' => $contributor->name,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.contributors.index'));

        $this->assertSame($originalPath, $contributor->fresh()->photo_path);
        Storage::disk('public')->assertExists($originalPath);
    }

    public function test_successful_deletion_removes_the_owned_photo(): void
    {
        Storage::fake('public');
        $photoPath = $this->fakePhoto()->store('contributors', 'public');
        $contributor = Contributor::factory()->create(['photo_path' => $photoPath]);

        $this->actingAs($this->admin())
            ->delete(route('admin.contributors.destroy', $contributor))
            ->assertRedirect(route('admin.contributors.index'));

        $this->assertDatabaseMissing('contributors', ['id' => $contributor->id]);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_blocked_deletion_due_to_contribution_history_preserves_the_photo(): void
    {
        Storage::fake('public');
        $photoPath = $this->fakePhoto()->store('contributors', 'public');
        $contributor = Contributor::factory()->create(['photo_path' => $photoPath]);
        EditionContribution::factory()->create(['contributor_id' => $contributor->id, 'committee_member_id' => null]);

        $this->actingAs($this->admin())
            ->delete(route('admin.contributors.destroy', $contributor))
            ->assertRedirect(route('admin.contributors.index'));

        $this->assertDatabaseHas('contributors', ['id' => $contributor->id]);
        Storage::disk('public')->assertExists($photoPath);
    }
}
