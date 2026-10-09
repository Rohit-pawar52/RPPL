<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\LanguageController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to routes/admin.php only (see bootstrap/app.php), never to the
 * public website, so a visitor's language cookie never changes the admin
 * panel and an admin's choice never changes the public site.
 *
 * The language is the signed-in user's own saved choice (users.locale); on
 * the pages seen before signing in (login) it is the admin language cookie.
 * Anything missing or not in LanguageController::LOCALES silently means the
 * default language (English), so a forged value can never do more than that.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->cookie(LanguageController::COOKIE_NAME);

        // Always set it, so a request never inherits the language of an earlier one in the same process.
        App::setLocale(in_array($locale, LanguageController::LOCALES, true) ? $locale : 'en');

        return $next($request);
    }
}
