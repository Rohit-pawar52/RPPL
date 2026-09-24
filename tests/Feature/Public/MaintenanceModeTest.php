<?php

namespace Tests\Feature\Public;

use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.44B3 — EnsurePublicSiteIsNotUnderMaintenance, applied only to
 * routes/web.php's public site. Proves the off/on behavior, the 503
 * status and message, that admin routes (a completely separate route
 * file) are never blocked, and that toggling the setting from the admin
 * panel takes effect immediately (SettingsService's existing cache
 * invalidation, no second maintenance-specific cache).
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);

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

    public function test_public_site_works_normally_when_maintenance_mode_is_off(): void
    {
        $this->get(route('public.home'))->assertOk();
        $this->get(route('public.matches.index'))->assertOk();
    }

    public function test_public_site_returns_503_when_maintenance_mode_is_on(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
    }

    public function test_maintenance_mode_blocks_every_public_route_including_guest_registration(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $this->get(route('public.matches.index'))->assertStatus(503);
        $this->get(route('public.player-registration.create'))->assertStatus(503);
        $this->post(route('public.player-registration.store'), [])->assertStatus(503);
    }

    public function test_configured_maintenance_message_renders_on_the_maintenance_page(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);
        app(SettingsService::class)->set('system.maintenance_message', 'Back online at 9 AM tomorrow.');

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
        $response->assertSee('Back online at 9 AM tomorrow.');
    }

    public function test_blank_maintenance_message_falls_back_to_a_default_message(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
        $response->assertSee('The website is currently under maintenance. Please check back shortly.');
    }

    public function test_maintenance_page_does_not_expose_admin_links(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
        $response->assertDontSee(route('admin.login'), false);
        $response->assertDontSee(route('admin.dashboard'), false);
    }

    public function test_admin_login_remains_accessible_during_public_maintenance(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $this->get(route('admin.login'))->assertOk();
    }

    public function test_admin_dashboard_and_settings_remain_accessible_during_public_maintenance(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings.index'))->assertOk();
    }

    public function test_admin_can_turn_maintenance_mode_off_from_settings_while_it_is_on(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.system.update'), [
            'maintenance_mode' => '0',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Asia/Kolkata',
            'committee_minimum_contribution' => '1000',
        ])->assertRedirect(route('admin.settings.index', ['tab' => 'system']));

        $this->assertFalse(app(SettingsService::class)->boolean('system.maintenance_mode'));
    }

    public function test_disabling_maintenance_mode_from_the_admin_panel_takes_effect_immediately_for_the_public_site(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);
        $this->get(route('public.home'))->assertStatus(503);

        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.settings.system.update'), [
            'maintenance_mode' => '0',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Asia/Kolkata',
            'committee_minimum_contribution' => '1000',
        ]);

        // No new request-scoped cache/container reset between these two
        // calls — this proves SettingsService's own flush() (already
        // called by set()) is sufficient, with no second
        // maintenance-specific cache layer needed.
        $this->get(route('public.home'))->assertOk();
    }

    public function test_enabling_maintenance_mode_from_the_admin_panel_takes_effect_immediately_for_the_public_site(): void
    {
        $this->get(route('public.home'))->assertOk();

        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.settings.system.update'), [
            'maintenance_mode' => '1',
            'currency' => 'INR',
            'currency_symbol' => '₹',
            'display_timezone' => 'Asia/Kolkata',
            'committee_minimum_contribution' => '1000',
        ]);

        $this->get(route('public.home'))->assertStatus(503);
    }
}
