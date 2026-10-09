<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The admin panel can be used in English or Hindi, chosen per user. The choice is stored on the user, follows
 * them to any device, never changes the public website, and a bad value can only mean English.
 */
class AdminLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin'])->id]);
    }

    public function test_switching_saves_the_language_on_the_account_and_returns_to_the_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->post(route('admin.language.switch', 'hi'))
            ->assertRedirect(route('admin.settings.index'))
            ->assertCookie('rppl_admin_locale', 'hi');

        $this->assertSame('hi', $admin->fresh()->locale);

        $this->actingAs($admin)->post(route('admin.language.switch', 'en'));
        $this->assertSame('en', $admin->fresh()->locale);
    }

    public function test_an_unknown_language_is_refused_and_a_foreign_referer_goes_to_the_dashboard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/language/fr')->assertNotFound();
        $this->assertNull($admin->fresh()->locale);

        $this->actingAs($admin)
            ->withHeader('referer', 'https://evil.example/phish')
            ->post(route('admin.language.switch', 'hi'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_panel_follows_the_signed_in_users_language_and_only_theirs(): void
    {
        $hindi = $this->admin(['locale' => 'hi']);
        $english = $this->admin(['locale' => null]);

        $this->actingAs($hindi)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<html lang="hi"', false)
            ->assertSee('aria-label="भाषा"', false);

        $this->actingAs($english)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('aria-label="Language"', false);
    }

    public function test_the_sign_in_page_follows_the_language_cookie_and_a_forged_one_means_english(): void
    {
        $this->withCookie('rppl_admin_locale', 'hi')->get(route('admin.login'))
            ->assertOk()
            ->assertSee('<html lang="hi"', false);

        $this->withCookie('rppl_admin_locale', '<script>')->get(route('admin.login'))
            ->assertOk()
            ->assertSee('<html lang="en"', false);
    }

    public function test_the_admin_language_never_changes_the_public_website(): void
    {
        $this->withCookie('rppl_admin_locale', 'hi')->get(route('public.home'))
            ->assertOk()
            ->assertSee('<html lang="en"', false);
    }

    public function test_dates_use_hindi_month_names_only_when_the_language_is_hindi(): void
    {
        app(SettingsService::class)->flush();
        $when = Carbon::parse('2026-10-03 18:50:00', 'UTC');

        $this->assertSame('04 Oct 2026, 12:20 AM', display_datetime($when, 'd M Y, h:i A'));

        app()->setLocale('hi');
        $this->assertSame('04 अक्टू. 2026, 12:20 AM', display_datetime($when, 'd M Y, h:i A'));
        // Machine formats (a datetime-local input's value) are never translated.
        $this->assertSame('2026-10-04T00:20', display_datetime($when, 'Y-m-d\TH:i'));
    }
}
