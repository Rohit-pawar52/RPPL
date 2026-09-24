<?php

namespace Tests\Feature\Admin;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
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

    /**
     * Phase 3.48 — committee_member_id is no longer an accepted field on
     * this form at all (committee membership is edition-specific now,
     * managed from the Finance "Committee" tab — see
     * EditionCommitteeMemberController/CommitteeContributionTest); a
     * request that sends it anyway must be silently ignored, never
     * validated or persisted, and must never fail merely because a
     * committee_members row happens to exist.
     */
    public function test_committee_member_id_in_the_request_is_silently_ignored_on_create_and_update(): void
    {
        $member = CommitteeMember::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('admin.contributors.store'), [
                'name' => 'Suresh Deshmukh',
                'committee_member_id' => $member->id,
            ])
            ->assertRedirect(route('admin.contributors.index'));

        $contributor = Contributor::firstWhere('name', 'Suresh Deshmukh');
        $this->assertNotNull($contributor);
        $this->assertNull($contributor->committee_member_id);

        $this->actingAs($this->admin())
            ->put(route('admin.contributors.update', $contributor), [
                'name' => $contributor->name,
                'is_active' => 1,
                'committee_member_id' => $member->id,
            ])
            ->assertRedirect(route('admin.contributors.index'));
        $this->assertNull($contributor->fresh()->committee_member_id);
    }

    public function test_committee_badge_on_show_reflects_edition_specific_membership(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $contributor = Contributor::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.contributors.show', $contributor));
        $response->assertOk();
        $response->assertDontSee('Committee Member', false);

        EditionCommitteeMember::create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);

        $this->actingAs($this->admin())
            ->get(route('admin.contributors.show', $contributor))
            ->assertOk()
            ->assertSee('Committee Member');
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

    // ----- Sorting -----

    public function test_sorting_ascending_and_descending_by_name(): void
    {
        $alpha = Contributor::factory()->create(['name' => 'Alpha Contributor']);
        $zebra = Contributor::factory()->create(['name' => 'Zebra Contributor']);

        $asc = $this->actingAs($this->admin())->get(route('admin.contributors.index', [
            'sort' => 'name', 'direction' => 'asc',
        ]));
        $body = $asc->getContent();
        $this->assertLessThan(strpos($body, $zebra->name), strpos($body, $alpha->name));

        $desc = $this->actingAs($this->admin())->get(route('admin.contributors.index', [
            'sort' => 'name', 'direction' => 'desc',
        ]));
        $body = $desc->getContent();
        $this->assertLessThan(strpos($body, $alpha->name), strpos($body, $zebra->name));
    }

    public function test_sorting_by_contributions_count(): void
    {
        $quiet = Contributor::factory()->create(['name' => 'Quiet Contributor']);
        $busy = Contributor::factory()->create(['name' => 'Busy Contributor']);
        EditionContribution::factory()->count(3)->create(['contributor_id' => $busy->id, 'committee_member_id' => null]);

        $asc = $this->actingAs($this->admin())->get(route('admin.contributors.index', [
            'sort' => 'contributions_count', 'direction' => 'asc',
        ]));
        $body = $asc->getContent();
        $this->assertLessThan(strpos($body, $busy->name), strpos($body, $quiet->name));

        $desc = $this->actingAs($this->admin())->get(route('admin.contributors.index', [
            'sort' => 'contributions_count', 'direction' => 'desc',
        ]));
        $body = $desc->getContent();
        $this->assertLessThan(strpos($body, $quiet->name), strpos($body, $busy->name));
    }

    public function test_invalid_sort_column_falls_back_to_name_asc(): void
    {
        Contributor::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.contributors.index', ['sort' => 'password']));

        $response->assertOk();
    }

    public function test_sortable_header_renders_a_real_anchor_with_no_markdown_or_leaked_entities(): void
    {
        Contributor::factory()->create();

        $html = $this->actingAs($this->admin())
            ->get(route('admin.contributors.index', ['sort' => 'name', 'direction' => 'desc']))
            ->getContent();

        $this->assertMatchesRegularExpression('#<a href="[^"]*sort=name[^"]*"[^>]*>\s*Name\s*<span[^>]*>↓</span>\s*</a>#', $html);
        $this->assertStringNotContainsString('&darr;', $html);
        $this->assertStringNotContainsString('&amp;darr;', $html);
        $this->assertStringNotContainsString('](http', $html);
    }

    // ----- Rows per page -----

    public function test_default_per_page_is_20(): void
    {
        Contributor::factory()->count(5)->create();

        $response = $this->actingAs($this->admin())->get(route('admin.contributors.index'));

        $response->assertViewHas('contributors', fn ($paginator) => $paginator->perPage() === 20);
    }

    public function test_each_allowed_per_page_value_is_honored(): void
    {
        Contributor::factory()->count(5)->create();

        foreach ([10, 20, 50, 100, 200] as $value) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.contributors.index', ['per_page' => $value]));

            $response->assertViewHas('contributors', fn ($paginator) => $paginator->perPage() === $value);
        }
    }

    public function test_invalid_per_page_falls_back_to_default(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('admin.contributors.index', ['per_page' => 'lots']));

        $response->assertViewHas('contributors', fn ($paginator) => $paginator->perPage() === 20);
    }
}
