<?php

namespace Tests\Feature\Settings;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin-editable theme beyond the three original colours: button hover
 * and text colour, link hover, hover highlight, the header colour and the
 * button shape. Each is stored in the database and reaches every page as a
 * CSS variable, so it can be changed in Settings with no code and no build;
 * an optional colour left empty is derived from the main colours instead.
 */
class ThemeCustomizationTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'application_name' => 'RPPL',
            'short_name' => 'RPPL',
            'primary_color' => '#15803d',
            'secondary_color' => '#64748b',
            'button_color' => '#15803d',
            'announcement_background_color' => '#15803d',
            'announcement_text_color' => '#ffffff',
            'header_color' => '#0b2e3f',
            'button_shape' => 'rounded',
        ], $overrides);
    }

    public function test_left_empty_the_hover_and_text_colours_are_worked_out_from_the_main_colours(): void
    {
        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('--rppl-button-hover: color-mix(in srgb, #15803d 85%, black);', false)
            ->assertSee('--rppl-link-hover: color-mix(in srgb, #15803d 80%, black);', false)
            ->assertSee('--rppl-hover-bg: color-mix(in srgb, #15803d 7%, white);', false)
            ->assertSee('--rppl-button-fg: #ffffff;', false)
            ->assertSee('--rppl-radius-btn: 0.625rem;', false)
            ->assertSee('--rppl-header: #0b2e3f;', false);
    }

    public function test_the_configured_theme_reaches_every_page_as_css_variables(): void
    {
        $settings = app(SettingsService::class);
        $settings->setMany([
            'general.button_hover_color' => '#112244',
            'general.button_text_color' => '#101010',
            'general.link_hover_color' => '#aa0000',
            'general.hover_color' => '#eeeeee',
            'general.header_color' => '#102030',
            'general.button_shape' => 'pill',
        ]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('--rppl-button-hover: #112244;', false)
            ->assertSee('--rppl-button-fg: #101010;', false)
            ->assertSee('--rppl-link-hover: #aa0000;', false)
            ->assertSee('--rppl-hover-bg: #eeeeee;', false)
            ->assertSee('--rppl-radius-btn: 9999px;', false)
            // The header colour re-points the whole navy palette (public header/footer and the admin sidebar).
            ->assertSee('--rppl-header: #102030;', false)
            ->assertSee('--color-navy-900: #102030;', false);

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('--rppl-button-hover: #112244;', false)
            ->assertSee('--color-navy-900: #102030;', false);
    }

    public function test_a_corrupted_optional_colour_or_shape_falls_back_safely(): void
    {
        Setting::query()->create(['group' => 'general', 'key' => 'button_hover_color', 'value' => '#000}</style><script>alert(1)</script>', 'type' => Setting::TYPE_COLOR]);
        Setting::query()->create(['group' => 'general', 'key' => 'hover_color', 'value' => 'not-a-color', 'type' => Setting::TYPE_COLOR]);
        Setting::query()->create(['group' => 'general', 'key' => 'button_shape', 'value' => 'url(javascript:alert(1))', 'type' => Setting::TYPE_STRING]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('not-a-color', false)
            ->assertDontSee('javascript:alert', false)
            ->assertSee('--rppl-button-hover: color-mix(in srgb, #15803d 85%, black);', false)
            ->assertSee('--rppl-hover-bg: color-mix(in srgb, #15803d 7%, white);', false)
            ->assertSee('--rppl-radius-btn: 0.625rem;', false);
    }

    public function test_the_theme_form_saves_every_colour_and_the_shape_and_clearing_an_optional_one_makes_it_automatic(): void
    {
        $admin = $this->admin();
        $settings = app(SettingsService::class);

        $this->actingAs($admin)
            ->put(route('admin.settings.general.update'), $this->payload([
                'button_hover_color' => '#112244',
                'button_text_color' => '#101010',
                'link_hover_color' => '#aa0000',
                'hover_color' => '#eeeeee',
                'header_color' => '#102030',
                'button_shape' => 'square',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['#112244', '#101010', '#aa0000', '#eeeeee', '#102030', 'square'],
            [
                $settings->get('general.button_hover_color'),
                $settings->get('general.button_text_color'),
                $settings->get('general.link_hover_color'),
                $settings->get('general.hover_color'),
                $settings->get('general.header_color'),
                $settings->get('general.button_shape'),
            ],
        );

        // Leaving the optional ones empty means "automatic" again.
        $this->actingAs($admin)
            ->put(route('admin.settings.general.update'), $this->payload([
                'button_hover_color' => '',
                'button_text_color' => '',
                'link_hover_color' => '',
                'hover_color' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($settings->get('general.button_hover_color'));
        $this->assertNull($settings->get('general.hover_color'));
        $this->get(route('public.home'))->assertSee('--rppl-button-hover: color-mix(in srgb, #15803d 85%, black);', false);
    }

    public function test_the_theme_form_refuses_an_invalid_colour_or_shape(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.general.update'), $this->payload(['button_hover_color' => 'red', 'button_shape' => 'blob']))
            ->assertSessionHasErrors(['button_hover_color', 'button_shape']);

        $this->assertNull(app(SettingsService::class)->get('general.button_hover_color'));
    }

    public function test_the_settings_page_has_the_theme_block_with_a_live_preview_and_auto_buttons(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.index', ['tab' => 'general']))
            ->assertOk()
            ->assertSee('Theme: colours, buttons and hover')
            ->assertSee('name="button_hover_color"', false)
            ->assertSee('name="button_text_color"', false)
            ->assertSee('name="link_hover_color"', false)
            ->assertSee('name="hover_color"', false)
            ->assertSee('name="header_color"', false)
            ->assertSee('name="button_shape"', false)
            ->assertSee('data-theme-auto="button_hover_color"', false)
            ->assertSee('id="theme-preview"', false);
    }
}
