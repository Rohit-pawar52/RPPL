<?php

namespace Tests\Feature\Public;

use App\Models\Announcement;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3.45 — the public notice ticker: Announcement::scopeActive()'s
 * visibility rule, the looping ticker markup, XSS-safe rendering, the
 * ticker's own theme colors, and that it never appears on admin/guest/
 * maintenance pages.
 */
class AnnouncementTickerTest extends TestCase
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

    // ----- Visibility rule -----

    public function test_disabled_announcement_is_hidden(): void
    {
        Announcement::factory()->create(['message' => 'Disabled notice', 'is_active' => false]);

        $this->get(route('public.home'))->assertDontSee('Disabled notice');
    }

    public function test_future_announcement_is_hidden(): void
    {
        Announcement::factory()->create(['message' => 'Future notice', 'starts_at' => now()->addDay()]);

        $this->get(route('public.home'))->assertDontSee('Future notice');
    }

    public function test_expired_announcement_is_hidden(): void
    {
        Announcement::factory()->create(['message' => 'Expired notice', 'ends_at' => now()->subDay()]);

        $this->get(route('public.home'))->assertDontSee('Expired notice');
    }

    public function test_currently_scheduled_window_announcement_is_visible(): void
    {
        Announcement::factory()->create([
            'message' => 'In-window notice',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->get(route('public.home'))->assertSee('In-window notice');
    }

    public function test_indefinite_active_announcement_is_visible(): void
    {
        Announcement::factory()->create(['message' => 'Indefinite notice', 'starts_at' => null, 'ends_at' => null]);

        $this->get(route('public.home'))->assertSee('Indefinite notice');
    }

    public function test_start_only_announcement_becomes_visible_once_started(): void
    {
        Announcement::factory()->create(['message' => 'Started notice', 'starts_at' => now()->subMinute(), 'ends_at' => null]);

        $this->get(route('public.home'))->assertSee('Started notice');
    }

    public function test_end_only_announcement_is_visible_until_it_ends(): void
    {
        Announcement::factory()->create(['message' => 'Ending soon notice', 'starts_at' => null, 'ends_at' => now()->addMinute()]);

        $this->get(route('public.home'))->assertSee('Ending soon notice');
    }

    public function test_multiple_announcements_render_ordered_by_sort_order_then_id(): void
    {
        $third = Announcement::factory()->create(['message' => 'Third', 'sort_order' => 2]);
        $first = Announcement::factory()->create(['message' => 'First', 'sort_order' => 0]);
        $second = Announcement::factory()->create(['message' => 'Second', 'sort_order' => 0]);

        $content = $this->get(route('public.home'))->getContent();

        $firstPos = strpos($content, 'First');
        $secondPos = strpos($content, 'Second');
        $thirdPos = strpos($content, 'Third');

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        // Same sort_order (0) for First/Second falls back to id order
        // (First was created before Second in this test).
        $this->assertLessThan($secondPos, $firstPos);
        $this->assertLessThan($thirdPos, $secondPos);
    }

    // ----- Zero / one / multiple rendering -----

    public function test_zero_active_announcements_renders_no_ticker_container(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertDontSee('rppl-ticker', false);
    }

    public function test_one_active_announcement_renders_the_ticker(): void
    {
        Announcement::factory()->create(['message' => 'Solo notice']);

        $response = $this->get(route('public.home'));

        $response->assertSee('rppl-ticker', false);
        $response->assertSee('Solo notice');
    }

    public function test_multiple_active_announcements_render_in_one_ticker(): void
    {
        Announcement::factory()->create(['message' => 'Notice A']);
        Announcement::factory()->create(['message' => 'Notice B']);

        $response = $this->get(route('public.home'));

        // Exactly one ticker region, both messages inside it.
        $this->assertSame(1, substr_count($response->getContent(), 'role="region" aria-label="Announcements"'));
        $response->assertSee('Notice A');
        $response->assertSee('Notice B');
    }

    // ----- XSS safety -----

    public function test_raw_html_in_an_announcement_is_escaped_on_the_public_ticker(): void
    {
        Announcement::factory()->create(['message' => '<script>alert(1)</script>']);

        $response = $this->get(route('public.home'));

        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_emoji_render_correctly_on_the_public_ticker(): void
    {
        Announcement::factory()->create(['message' => '🏏 Final Match — 1 January 📢']);

        $this->get(route('public.home'))->assertSee('🏏 Final Match — 1 January 📢');
    }

    // ----- Theme colors -----

    public function test_announcement_background_and_text_settings_render_into_runtime_css_variables(): void
    {
        app(SettingsService::class)->set('general.announcement_background_color', '#112233');
        app(SettingsService::class)->set('general.announcement_text_color', '#eeddcc');

        $response = $this->get(route('public.home'));

        $response->assertSee('--rppl-announcement-bg: #112233;', false);
        $response->assertSee('--rppl-announcement-text: #eeddcc;', false);
    }

    public function test_malformed_announcement_color_falls_back_safely(): void
    {
        Setting::query()->create([
            'group' => 'general',
            'key' => 'announcement_background_color',
            'value' => 'not-a-color',
            'type' => Setting::TYPE_COLOR,
        ]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('--rppl-announcement-bg: #2563eb;', false);
        $response->assertDontSee('not-a-color', false);
    }

    public function test_general_settings_can_update_both_announcement_colors(): void
    {
        $response = $this->actingAs($this->admin())->put(route('admin.settings.general.update'), [
            'application_name' => 'RajaBhoj Pawar Premier League',
            'short_name' => 'RPPL',
            'primary_color' => '#2563eb',
            'secondary_color' => '#737373',
            'button_color' => '#2563eb',
            'announcement_background_color' => '#334455',
            'announcement_text_color' => '#f0f0f0',
        ]);

        $response->assertRedirect(route('admin.settings.index', ['tab' => 'general']));

        $settings = app(SettingsService::class);
        $this->assertSame('#334455', $settings->get('general.announcement_background_color'));
        $this->assertSame('#f0f0f0', $settings->get('general.announcement_text_color'));
    }

    public function test_changing_announcement_colors_is_reflected_on_the_next_request_without_a_build(): void
    {
        $this->get(route('public.home'))->assertSee('--rppl-announcement-bg: #2563eb;', false);

        app(SettingsService::class)->set('general.announcement_background_color', '#abcdef');

        $this->get(route('public.home'))->assertSee('--rppl-announcement-bg: #abcdef;', false);
    }

    // ----- Placement exclusions -----

    public function test_maintenance_page_does_not_render_the_ticker(): void
    {
        Announcement::factory()->create(['message' => 'Should not appear']);
        app(SettingsService::class)->set('system.maintenance_mode', true);

        $response = $this->get(route('public.home'));

        $response->assertStatus(503);
        $response->assertDontSee('rppl-ticker', false);
        $response->assertDontSee('Should not appear');
    }

    public function test_admin_pages_do_not_render_the_public_ticker(): void
    {
        Announcement::factory()->create(['message' => 'Admin should not see this']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('rppl-ticker', false);
        $response->assertDontSee('Admin should not see this');
    }

    public function test_guest_login_page_does_not_render_the_public_ticker(): void
    {
        Announcement::factory()->create(['message' => 'Should not appear on login']);

        $response = $this->get(route('admin.login'));

        $response->assertOk();
        $response->assertDontSee('rppl-ticker', false);
    }
}
