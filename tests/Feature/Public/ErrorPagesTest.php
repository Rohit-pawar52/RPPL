<?php

namespace Tests\Feature\Public;

use App\Http\Controllers\Public\LanguageController;
use App\Services\Settings\SettingsService;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The friendly error pages: 404 / 403 / 419 / 429 on the public layout (a way
 * home, links to matches, a player search on the 404) and 500 / 503 as
 * self-contained pages that need neither the database nor the settings.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([403, 419, 429, 500, 503] as $status) {
            Route::middleware('web')->get('/_test/abort-'.$status, fn () => abort($status));
        }
    }

    public function test_an_unknown_address_gets_a_helpful_404_with_a_way_home_and_a_player_search(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('<title>Page not found &middot;', false)
            ->assertSee('href="'.route('public.home').'"', false)
            ->assertSee('href="'.route('public.matches.index').'"', false)
            ->assertSee('action="'.route('public.players.index').'"', false)
            ->assertSee('name="search"', false);

        // A record that does not exist is the same friendly page, not Laravel's bare one.
        $this->get(route('public.matches.show', 999999))
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    public function test_the_404_follows_the_saved_language_even_though_no_language_middleware_ran(): void
    {
        // The browser sends the cookie encrypted; an unknown address is answered
        // before the cookie middleware, so the page must read it itself.
        $encrypted = Crypt::encryptString(CookieValuePrefix::create(LanguageController::COOKIE_NAME, Crypt::getKey()).'hi');

        $this->withUnencryptedCookie(LanguageController::COOKIE_NAME, $encrypted)
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('पेज नहीं मिला')
            ->assertDontSee('Page not found');
    }

    public function test_forbidden_expired_and_throttled_requests_get_their_own_message(): void
    {
        $this->get('/_test/abort-403')->assertForbidden()->assertSee('Access denied');
        $this->get('/_test/abort-419')->assertStatus(419)->assertSee('Page expired');
        $this->get('/_test/abort-429')->assertStatus(429)->assertSee('Too many requests');
    }

    public function test_server_error_and_down_pages_stand_on_their_own_without_the_site_frame(): void
    {
        foreach ([500 => 'Something went wrong', 503 => 'We will be back soon'] as $status => $title) {
            $this->get('/_test/abort-'.$status)
                ->assertStatus($status)
                ->assertSee($title)
                // No header / footer / settings: nothing here can fail with the database.
                ->assertDontSee('data-public-header', false)
                ->assertDontSee('--rppl-primary:', false);
        }
    }

    public function test_the_maintenance_page_follows_the_saved_language_and_keeps_the_admin_message(): void
    {
        app(SettingsService::class)->flush();
        app(SettingsService::class)->set('system.maintenance_mode', true);

        // Maintenance is answered before the language middleware, so the page reads the cookie itself.
        $this->withCookie(LanguageController::COOKIE_NAME, 'hi')
            ->get(route('public.home'))
            ->assertStatus(503)
            ->assertSee('हम जल्द ही लौटेंगे')
            ->assertSee('वेबसाइट पर अभी रखरखाव का काम चल रहा है');

        app(SettingsService::class)->set('system.maintenance_message', 'Back at 9 AM.');

        $this->withCookie(LanguageController::COOKIE_NAME, 'en')
            ->get(route('public.home'))
            ->assertStatus(503)
            ->assertSee('We will be back soon')
            ->assertSee('Back at 9 AM.');
    }
}
