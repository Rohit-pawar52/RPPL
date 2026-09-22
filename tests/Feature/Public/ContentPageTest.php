<?php

namespace Tests\Feature\Public;

use App\Models\Announcement;
use App\Models\ContentPage;
use App\Models\Role;
use App\Models\User;
use App\Services\ContentPage\ContentPageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3.46 — the public content pages (Privacy Policy/Terms/FAQs):
 * one reusable template shared by all three explicit routes, the
 * inactive-means-404 rule, footer link visibility, safe Markdown
 * rendering (XSS regression), and that the rest of the public layout
 * (branding/theme/announcement ticker) still surrounds the page.
 */
class ContentPageTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        // phpunit.xml runs the `array` cache driver, which persists for
        // the lifetime of the test process — RefreshDatabase resets the
        // content_pages TABLE between tests, but not
        // ContentPageService's own cache entry, so it must be cleared
        // explicitly here too.
        app(ContentPageService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    // ----- One reusable template, three routes -----

    public function test_public_privacy_route_uses_the_reusable_content_page_template(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY, 'title' => 'Privacy Policy']);

        $this->get(route('public.privacy-policy'))
            ->assertOk()
            ->assertViewIs('public.content-page')
            ->assertSee('Privacy Policy');
    }

    public function test_public_terms_route_uses_the_same_reusable_template(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_TERMS_CONDITIONS, 'title' => 'Terms & Conditions']);

        $this->get(route('public.terms-conditions'))
            ->assertOk()
            ->assertViewIs('public.content-page')
            ->assertSee('Terms & Conditions');
    }

    public function test_public_faq_route_uses_the_same_reusable_template(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_FAQS, 'title' => 'Frequently Asked Questions']);

        $this->get(route('public.faqs'))
            ->assertOk()
            ->assertViewIs('public.content-page')
            ->assertSee('Frequently Asked Questions');
    }

    // ----- Inactive behavior -----

    public function test_inactive_page_returns_404(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY, 'is_active' => false]);

        $this->get(route('public.privacy-policy'))->assertNotFound();
    }

    public function test_missing_canonical_row_also_returns_404(): void
    {
        // No ContentPage rows created at all.
        $this->get(route('public.faqs'))->assertNotFound();
    }

    // ----- Footer integration -----

    public function test_inactive_page_disappears_from_the_footer(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY, 'title' => 'Privacy Policy', 'is_active' => false]);

        $this->get(route('public.home'))->assertDontSee('href="'.route('public.privacy-policy').'"', false);
    }

    public function test_active_pages_appear_in_the_footer(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY, 'title' => 'Privacy Policy', 'is_active' => true]);
        ContentPage::factory()->create(['type' => ContentPage::TYPE_FAQS, 'title' => 'Frequently Asked Questions', 'is_active' => true]);

        $response = $this->get(route('public.home'));

        $response->assertSee('href="'.route('public.privacy-policy').'"', false);
        $response->assertSee('href="'.route('public.faqs').'"', false);
    }

    public function test_footer_queries_content_pages_only_once_per_request(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);
        Announcement::factory()->create();

        DB::enableQueryLog();
        $this->get(route('public.home'));
        $queries = collect(DB::getQueryLog())->filter(
            fn ($query) => str_contains($query['query'], 'content_pages')
        );
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }

    // ----- Markdown rendering -----

    public function test_markdown_formatting_renders_correctly(): void
    {
        ContentPage::factory()->create([
            'type' => ContentPage::TYPE_FAQS,
            'content' => "## What is RPPL?\n\nA local **cricket** tournament.\n\n- Teams\n- Players",
        ]);

        $response = $this->get(route('public.faqs'));

        $response->assertSee('<h2>What is RPPL?</h2>', false);
        $response->assertSee('<strong>cricket</strong>', false);
        $response->assertSee('<li>Teams</li>', false);
    }

    // ----- Security -----

    public function test_raw_script_in_content_does_not_render_or_execute(): void
    {
        ContentPage::factory()->create([
            'type' => ContentPage::TYPE_PRIVACY_POLICY,
            'content' => "## Heading\n\n<script>alert(1)</script>",
        ]);

        $response = $this->get(route('public.privacy-policy'));

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_javascript_link_scheme_is_neutralized(): void
    {
        ContentPage::factory()->create([
            'type' => ContentPage::TYPE_FAQS,
            'content' => '[click me](javascript:alert(1))',
        ]);

        $response = $this->get(route('public.faqs'));

        $response->assertDontSee('href="javascript:alert(1)"', false);
    }

    public function test_unicode_and_emoji_content_renders_correctly(): void
    {
        ContentPage::factory()->create([
            'type' => ContentPage::TYPE_FAQS,
            'content' => '## 🏏 Rules',
        ]);

        $this->get(route('public.faqs'))->assertSee('🏏 Rules');
    }

    // ----- Layout integration -----

    public function test_content_page_is_surrounded_by_the_normal_public_layout(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);
        Announcement::factory()->create(['message' => 'Layout check notice']);

        $response = $this->get(route('public.privacy-policy'));

        $response->assertOk();
        // Branding/theme variables (Phase 3.44).
        $response->assertSee('--rppl-primary:', false);
        // The announcement ticker (Phase 3.45) still surrounds the page.
        $response->assertSee('Layout check notice');
    }

    // ----- Cache invalidation -----

    public function test_updating_a_page_invalidates_the_footer_cache_immediately(): void
    {
        $page = ContentPage::factory()->create([
            'type' => ContentPage::TYPE_PRIVACY_POLICY,
            'title' => 'Privacy Policy',
            'is_active' => true,
        ]);

        // Warms the cache.
        $this->get(route('public.home'))->assertSee('href="'.route('public.privacy-policy').'"', false);

        $this->actingAs($this->admin())->put(route('admin.content-pages.update', $page), [
            'title' => 'Privacy Policy',
            'is_active' => '0',
        ]);

        $this->get(route('public.home'))->assertDontSee('href="'.route('public.privacy-policy').'"', false);
    }

    public function test_content_page_service_flush_removes_the_cached_footer_list(): void
    {
        ContentPage::factory()->create(['type' => ContentPage::TYPE_PRIVACY_POLICY]);

        $service = app(ContentPageService::class);
        $first = $service->activeFooterPages();
        $this->assertCount(1, $first);

        ContentPage::factory()->create(['type' => ContentPage::TYPE_FAQS]);
        // Without flush(), the cached list would still show only 1.
        $service->flush();

        $this->assertCount(2, $service->activeFooterPages());
    }
}
