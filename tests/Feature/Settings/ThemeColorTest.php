<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\ForegroundContrast;
use App\Support\HexColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.44B4 — dynamic theme color consumption. Proves the runtime
 * CSS-variable bridge (never generated Tailwind classes, never
 * build-dependent), the malformed-color defensive fallback, the
 * button/avatar foreground contrast strategy, and that a semantic
 * (non-brand) surface is never accidentally converted.
 */
class ThemeColorTest extends TestCase
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

    // ----- Defaults / fallback -----

    public function test_default_theme_values_render_when_settings_table_is_empty(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('--rppl-primary: #2563eb;', false);
        $response->assertSee('--rppl-secondary: #737373;', false);
        $response->assertSee('--rppl-button: #2563eb;', false);
    }

    public function test_configured_primary_color_renders_into_the_runtime_theme_variable(): void
    {
        app(SettingsService::class)->set('general.primary_color', '#112233');

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('--rppl-primary: #112233;', false);
    }

    public function test_configured_secondary_color_renders_into_the_runtime_theme_variable(): void
    {
        app(SettingsService::class)->set('general.secondary_color', '#445566');

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('--rppl-secondary: #445566;', false);
    }

    public function test_configured_button_color_renders_into_the_runtime_theme_variable(): void
    {
        app(SettingsService::class)->set('general.button_color', '#998877');

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('--rppl-button: #998877;', false);
    }

    public function test_malformed_persisted_color_falls_back_safely_to_the_registry_default(): void
    {
        // Simulates legacy/manual DB corruption — never possible through
        // the validated admin form (UpdateGeneralSettingsRequest).
        Setting::query()->create(['group' => 'general', 'key' => 'primary_color', 'value' => 'not-a-color', 'type' => Setting::TYPE_COLOR]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('--rppl-primary: #2563eb;', false);
        $response->assertDontSee('not-a-color', false);
    }

    public function test_arbitrary_css_cannot_be_injected_through_a_corrupted_color_value(): void
    {
        Setting::query()->create([
            'group' => 'general',
            'key' => 'button_color',
            'value' => '#000}</style><script>alert(1)</script>',
            'type' => Setting::TYPE_COLOR,
        ]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        // Falls back to the registered default instead of the corrupted value.
        $response->assertSee('--rppl-button: #2563eb;', false);
    }

    // ----- HexColor / ForegroundContrast units -----

    public function test_hex_color_sanitize_accepts_valid_six_digit_hex(): void
    {
        $this->assertSame('#AaBbCc', HexColor::sanitize('#AaBbCc', '#000000'));
    }

    public function test_hex_color_sanitize_rejects_malformed_values(): void
    {
        $this->assertSame('#000000', HexColor::sanitize('blue', '#000000'));
        $this->assertSame('#000000', HexColor::sanitize('#fff', '#000000'));
        $this->assertSame('#000000', HexColor::sanitize(null, '#000000'));
        $this->assertSame('#000000', HexColor::sanitize('#gggggg', '#000000'));
    }

    public function test_foreground_contrast_picks_a_readable_color_for_representative_backgrounds(): void
    {
        $this->assertSame('#000000', ForegroundContrast::for('#FFFFFF'));
        $this->assertSame('#ffffff', ForegroundContrast::for('#000000'));
        $this->assertSame('#ffffff', ForegroundContrast::for('#2563EB'));
        // A light representative color should get a dark foreground.
        $this->assertSame('#000000', ForegroundContrast::for('#FDE047'));
        // A dark representative color should get a light foreground.
        $this->assertSame('#ffffff', ForegroundContrast::for('#1E293B'));
    }

    // ----- Consumption on representative surfaces -----

    public function test_a_primary_action_button_consumes_the_dynamic_button_theme_class(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.teams.create'));

        $response->assertOk();
        $response->assertSee('theme-button', false);
        $response->assertDontSee('bg-blue-600', false);
    }

    public function test_active_navigation_consumes_the_dynamic_primary_theme_class(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.editions.index'));

        $response->assertOk();
        $response->assertSee('theme-primary-soft-bg', false);
        $response->assertSee('theme-primary-text', false);
    }

    public function test_destructive_action_is_not_converted_to_the_dynamic_button_theme(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.data-cleanup.index'));

        $response->assertOk();
        // Destructive buttons stay on the semantic red palette, never theme-button.
        $response->assertSee('bg-red-50', false);
    }

    public function test_status_badges_remain_semantic_and_are_never_converted_to_theme_classes(): void
    {
        // 'refunded'/'scheduled' status-badge colors intentionally stay
        // hardcoded blue (a semantic status color, not a brand accent).
        $component = view('components.status-badge', ['status' => 'scheduled'])->render();

        $this->assertStringContainsString('bg-blue-50', $component);
        $this->assertStringNotContainsString('theme-', $component);
    }

    // ----- Every themed layout receives theme variables -----

    public function test_public_layout_receives_theme_variables(): void
    {
        $this->get(route('public.home'))->assertSee('--rppl-primary:', false);
    }

    public function test_admin_layout_receives_theme_variables(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertSee('--rppl-primary:', false);
    }

    public function test_guest_login_layout_receives_theme_variables(): void
    {
        $this->get(route('admin.login'))->assertSee('--rppl-primary:', false);
    }

    public function test_maintenance_page_receives_theme_variables(): void
    {
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
        $response->assertSee('--rppl-primary:', false);
    }

    // ----- No build dependency -----

    public function test_changing_a_color_setting_changes_rendered_css_on_the_very_next_request(): void
    {
        $this->get(route('public.home'))->assertSee('--rppl-primary: #2563eb;', false);

        app(SettingsService::class)->set('general.primary_color', '#abcdef');

        // No cache clear, no artisan command, no rebuild between these
        // two requests — SettingsService's own flush() (already called
        // by set()) is the only thing making this work.
        $this->get(route('public.home'))->assertSee('--rppl-primary: #abcdef;', false);
    }

    public function test_theme_colors_are_never_compiled_into_tailwind_utility_class_names(): void
    {
        app(SettingsService::class)->set('general.primary_color', '#abcdef');

        $response = $this->get(route('public.home'));

        // The color must appear only as a CSS custom-property VALUE,
        // never inside a generated class name like bg-[#abcdef].
        $response->assertDontSee('[#abcdef]', false);
    }
}
