<?php

namespace Tests\Feature\Settings;

use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.44B3 — proves the approved Settings values actually reach
 * presentation surfaces (public/admin layouts, PDFs) via
 * BrandingComposer/App\Support\Branding, with correct empty-table
 * fallback behavior and no broken-image/broken-favicon regressions.
 */
class BrandingConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        Role::create(['name' => 'Scorer', 'slug' => 'scorer']);

        // phpunit.xml runs the `array` cache driver, which persists for
        // the lifetime of the test process — RefreshDatabase resets the
        // settings TABLE between tests, but not SettingsService's own
        // cache entry, so it must be cleared explicitly here too.
        app(SettingsService::class)->flush();
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => $this->adminRole->id]);
    }

    // ----- Fallback (empty settings table) -----

    public function test_public_home_falls_back_to_the_registry_default_application_name_with_no_settings_rows(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('RajaBhoj Pawar Premier League');
    }

    public function test_missing_logo_renders_no_broken_image_on_the_public_header(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('<img', false);
    }

    public function test_missing_favicon_does_not_link_to_the_known_broken_favicon_ico(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('favicon.ico', false);
    }

    public function test_tagline_renders_nothing_extra_when_not_configured(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Local cricket tournament scores, fixtures, and standings.');
    }

    public function test_footer_fallback_uses_the_dynamic_application_name_and_current_year(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee(now()->year.' RajaBhoj Pawar Premier League', false);
    }

    // ----- Configured values -----

    public function test_configured_application_name_appears_on_public_branding(): void
    {
        app(SettingsService::class)->set('general.application_name', 'Custom League Name');

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Custom League Name');
        $response->assertDontSee('RajaBhoj Pawar Premier League');
    }

    public function test_configured_short_name_appears_in_the_public_header_and_admin_header(): void
    {
        app(SettingsService::class)->set('general.short_name', 'CUSTOM');

        $this->get(route('public.home'))->assertSee('CUSTOM');
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertSee('CUSTOM Admin');
    }

    public function test_configured_tagline_renders_on_the_public_home_page(): void
    {
        app(SettingsService::class)->set('general.tagline', 'One League. One Family.');

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('One League. One Family.');
    }

    public function test_configured_footer_text_renders_instead_of_the_default(): void
    {
        app(SettingsService::class)->set('public.footer_text', 'A custom footer message.');

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('A custom footer message.');
    }

    public function test_configured_logo_renders_on_the_public_header(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->create('logo.png', 100, 'image/png')->store('branding', 'public');
        app(SettingsService::class)->set('general.logo_path', $logo);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url($logo), false);
    }

    public function test_configured_favicon_renders_a_link_tag(): void
    {
        Storage::fake('public');
        $favicon = UploadedFile::fake()->create('favicon.ico', 10, 'image/x-icon')->store('branding', 'public');
        app(SettingsService::class)->set('general.favicon_path', $favicon);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('rel="icon"', false);
        $response->assertSee(Storage::disk('public')->url($favicon), false);
    }

    // ----- Contact -----

    public function test_contact_details_only_render_when_configured(): void
    {
        $withoutContact = $this->get(route('public.home'));
        $withoutContact->assertOk();
        $withoutContact->assertDontSee('mailto:');

        app(SettingsService::class)->set('contact.email', 'info@rppl.test');

        $withContact = $this->get(route('public.home'));
        $withContact->assertOk();
        $withContact->assertSee('mailto:info@rppl.test', false);
    }

    // ----- PDF branding -----

    public function test_edition_report_pdf_uses_the_configured_application_name(): void
    {
        app(SettingsService::class)->set('general.application_name', 'Custom League Name');

        $edition = Edition::factory()->create();

        $view = view('admin.editions.report-pdf', [
            'edition' => $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']),
            'registrationCounts' => collect(),
            'paidRegistrationFees' => 0.0,
            'matchStatusCounts' => collect(),
            'teams' => collect(),
            'matches' => collect(),
            'standings' => ['standings' => []],
            'leaderboard' => ['topRunScorers' => [], 'topWicketTakers' => []],
        ])->render();

        $this->assertStringContainsString('Custom League Name', $view);
        $this->assertStringNotContainsString('RPPL Tournament', $view);
    }

    public function test_contribution_receipt_view_uses_the_configured_application_name(): void
    {
        app(SettingsService::class)->set('general.application_name', 'Custom League Name');

        $contribution = EditionContribution::factory()->create();

        $view = view('admin.edition-contributions.receipt', ['contribution' => $contribution])->render();

        $this->assertStringContainsString('Custom League Name', $view);
        $this->assertStringNotContainsString('RPPL Tournament', $view);
    }
}
