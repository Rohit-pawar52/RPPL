<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Switches the admin panel between English and Hindi. A signed-in user's
 * choice is saved on their account (so it follows them to any device); the
 * cookie also carries it to the sign-in page. Public-site language is a
 * separate setting (Public\LanguageController).
 */
class LanguageController extends Controller
{
    public const LOCALES = ['en', 'hi'];

    public const COOKIE_NAME = 'rppl_admin_locale';

    public function switch(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, self::LOCALES, true), 404);

        $request->user()?->forceFill(['locale' => $locale])->save();

        Cookie::queue(self::COOKIE_NAME, $locale, 60 * 24 * 365 * 5); // ~5 years

        return redirect()->to($this->safeRedirectTarget($request));
    }

    /**
     * Back to the page the user was on, but only when that page is on this
     * same host; anything else goes to the dashboard.
     */
    private function safeRedirectTarget(Request $request): string
    {
        $referer = $request->headers->get('referer');

        if (! $referer || parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return route('admin.dashboard');
        }

        return $referer;
    }
}
