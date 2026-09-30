<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bilingual localization V1 — the shared foundation (SetPublicLocale
 * middleware, LanguageController, the rppl_locale cookie, and the
 * header's language switcher). Page-specific translation coverage lives
 * in each page's own test file; this proves the switching MECHANISM
 * itself, independent of which strings are actually translated.
 */
class PublicLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_locale_is_english(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('lang="en"', false);
        $response->assertSee('Matches');
    }

    public function test_switching_to_hindi_sets_the_locale_cookie(): void
    {
        $response = $this->post(route('public.language.switch', 'hi'));

        $response->assertRedirect();
        $response->assertCookie('rppl_locale', 'hi');
    }

    public function test_switching_to_english_sets_the_locale_cookie(): void
    {
        $response = $this->withCookie('rppl_locale', 'hi')
            ->post(route('public.language.switch', 'en'));

        $response->assertRedirect();
        $response->assertCookie('rppl_locale', 'en');
    }

    public function test_hindi_cookie_renders_hindi_navigation_and_html_lang(): void
    {
        $response = $this->withCookie('rppl_locale', 'hi')->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('lang="hi"', false);
        $response->assertSee('मैच'); // "Matches"
    }

    public function test_locale_persists_across_a_second_request_with_the_same_cookie(): void
    {
        $this->withCookie('rppl_locale', 'hi')->get(route('public.home'))->assertSee('lang="hi"', false);
        $this->withCookie('rppl_locale', 'hi')->get(route('public.matches.index'))->assertSee('lang="hi"', false);
    }

    public function test_an_invalid_locale_in_the_route_is_rejected(): void
    {
        // {locale} is constrained via ->whereIn() to exactly ['en','hi']
        // in routes/web.php — anything else never reaches the controller.
        $this->post('/language/fr')->assertNotFound();
        $this->post('/language/../../etc')->assertNotFound();
    }

    public function test_a_tampered_cookie_value_falls_back_to_english_silently(): void
    {
        $response = $this->withCookie('rppl_locale', 'de')->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('lang="en"', false);
    }

    public function test_admin_pages_are_unaffected_by_the_public_locale_cookie(): void
    {
        // SetPublicLocale is only ever registered on routes/web.php's
        // public group — routes/admin.php never sees it.
        $response = $this->withCookie('rppl_locale', 'hi')->get(route('admin.login'));

        $response->assertOk();
        $response->assertDontSee('मैच');
    }

    public function test_public_urls_are_unchanged_regardless_of_locale(): void
    {
        foreach (['en', 'hi'] as $locale) {
            $this->withCookie('rppl_locale', $locale)->get(route('public.matches.index'))->assertOk();
            $this->withCookie('rppl_locale', $locale)->get(route('public.rules.index'))->assertOk();
            $this->withCookie('rppl_locale', $locale)->get(route('public.videos.index'))->assertOk();
        }
    }

    public function test_header_shows_both_language_options(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertSee('English');
        $response->assertSee('हिन्दी');
    }

    public function test_switch_redirects_back_to_a_same_origin_referer(): void
    {
        $response = $this->post(route('public.language.switch', 'hi'), [], [
            'referer' => route('public.matches.index'),
        ]);

        $response->assertRedirect(route('public.matches.index'));
    }

    /**
     * An attacker-controlled Referer pointing at an external host must
     * never be trusted as a redirect target (open-redirect protection).
     */
    public function test_switch_ignores_an_external_referer_and_falls_back_to_home(): void
    {
        $response = $this->post(route('public.language.switch', 'hi'), [], [
            'referer' => 'https://evil.example.com/phishing',
        ]);

        $response->assertRedirect(route('public.home'));
    }
}
