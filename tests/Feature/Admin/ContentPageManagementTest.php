<?php

namespace Tests\Feature\Admin;

use App\Models\ContentPage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Demo\DemoContentPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.46 — admin management of the three fixed content pages.
 * Proves authorization (the "manage-tournament" Gate, same as
 * Settings/Reports/DataCleanup), deterministic demo seeding, per-tab
 * updates, and that `type` can never be changed through the request.
 */
class ContentPageManagementTest extends TestCase
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

    // ----- Authorization -----

    public function test_admin_can_access_content_pages(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);

        $this->actingAs($this->admin())->get(route('admin.content-pages.index'))->assertOk();
    }

    public function test_scorer_receives_403(): void
    {
        $page = ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);

        $this->actingAs($this->scorer())->get(route('admin.content-pages.index'))->assertForbidden();
        $this->actingAs($this->scorer())->put(route('admin.content-pages.update', $page), [
            'title' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.content-pages.index'))->assertRedirect(route('admin.login'));
    }

    // ----- Demo seeding -----

    public function test_exactly_the_three_supported_canonical_types_exist_after_demo_seeding(): void
    {
        $this->seed(DemoContentPageSeeder::class);

        $this->assertSame(3, ContentPage::count());
        $this->assertEqualsCanonicalizing(
            ContentPage::TYPES,
            ContentPage::pluck('type')->all()
        );
    }

    public function test_demo_seeding_is_idempotent(): void
    {
        $this->seed(DemoContentPageSeeder::class);
        $this->seed(DemoContentPageSeeder::class);

        $this->assertSame(3, ContentPage::count());
    }

    /**
     * Pre-UAT audit fix: the seeded content used to end every page with
     * the literal sentence "This is placeholder starter content — edit
     * it here before publishing.", visible to any real site visitor
     * (these pages are linked from the public footer on every page).
     */
    public function test_demo_seeded_content_does_not_announce_itself_as_placeholder(): void
    {
        $this->seed(DemoContentPageSeeder::class);

        foreach (ContentPage::all() as $page) {
            $this->assertStringNotContainsString('placeholder starter content', (string) $page->content);
        }
    }

    // ----- Updates -----

    public function test_admin_can_update_privacy_policy(): void
    {
        $page = ContentPage::factory()->create([
            'type' => ContentPage::TYPE_PRIVACY_POLICY,
            'title' => 'Privacy Policy',
        ]);

        $response = $this->actingAs($this->admin())->put(route('admin.content-pages.update', $page), [
            'title' => 'Our Privacy Policy',
            'content' => '## Data We Collect',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.content-pages.index', ['tab' => ContentPage::TYPE_PRIVACY_POLICY]));
        $this->assertDatabaseHas('content_pages', [
            'id' => $page->id,
            'title' => 'Our Privacy Policy',
            'content' => '## Data We Collect',
        ]);
    }

    public function test_admin_can_update_terms_and_conditions(): void
    {
        $page = ContentPage::factory()->create(['type' => ContentPage::TYPE_TERMS_CONDITIONS]);

        $this->actingAs($this->admin())->put(route('admin.content-pages.update', $page), [
            'title' => 'Updated Terms',
            'content' => '## Rules',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('content_pages', ['id' => $page->id, 'title' => 'Updated Terms']);
    }

    public function test_admin_can_update_faqs(): void
    {
        $page = ContentPage::factory()->create(['type' => ContentPage::TYPE_FAQS]);

        $this->actingAs($this->admin())->put(route('admin.content-pages.update', $page), [
            'title' => 'Updated FAQs',
            'content' => '## What is RPPL?',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('content_pages', ['id' => $page->id, 'title' => 'Updated FAQs']);
    }

    public function test_admin_can_disable_a_page(): void
    {
        $page = ContentPage::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin())->put(route('admin.content-pages.update', $page), [
            'title' => $page->title,
            'is_active' => '0',
        ]);

        $this->assertFalse($page->fresh()->is_active);
    }

    // ----- Type is never editable -----

    public function test_canonical_type_cannot_be_changed_through_the_request(): void
    {
        $page = ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);

        $this->actingAs($this->admin())->put(route('admin.content-pages.update', $page), [
            'title' => 'Still Privacy Policy',
            'type' => ContentPage::TYPE_FAQS,
            'is_active' => '1',
        ]);

        $this->assertSame(ContentPage::TYPE_PRIVACY_POLICY, $page->fresh()->type);
    }

    // ----- Sidebar -----

    public function test_content_pages_nav_item_is_visible_to_admin_and_hidden_from_scorer(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Content Pages');

        $this->actingAs($this->scorer())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Content Pages');
    }
}
