<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The public visitor language switcher (English/हिन्दी). No login, no
 * database row — the preference lives entirely in a long-lived cookie
 * read by SetPublicLocale on every subsequent public request. Never
 * applies to Admin/Scorer (routes/admin.php never registers
 * SetPublicLocale).
 */
class LanguageController extends Controller
{
    public const LOCALES = ['en', 'hi'];

    public const COOKIE_NAME = 'rppl_locale';

    /**
     * The route itself already constrains {locale} to LOCALES via
     * ->whereIn() (routes/web.php) — this const is the single source of
     * truth both the route constraint and SetPublicLocale read, so the
     * two can never quietly drift apart.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        Cookie::queue(self::COOKIE_NAME, $locale, 60 * 24 * 365 * 5); // ~5 years

        return redirect()->to($this->safeRedirectTarget($request));
    }

    /**
     * Never trusts the Referer header as-is (an attacker-controlled
     * request could set it to any external URL — an open-redirect
     * surface) — only a same-origin path is honored; anything else
     * (missing, external host, malformed) falls back to the homepage.
     */
    private function safeRedirectTarget(Request $request): string
    {
        $referer = $request->headers->get('referer');

        if (! $referer) {
            return route('public.home');
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);

        if ($refererHost !== $request->getHost()) {
            return route('public.home');
        }

        return $referer;
    }
}
