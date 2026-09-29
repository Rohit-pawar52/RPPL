<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Public\LanguageController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied only to routes/web.php's public tournament website (see its
 * own Route::middleware() wrapper) — never to routes/admin.php, so an
 * Admin/Scorer session is never affected by a visitor's language
 * cookie. Reads the persisted preference (LanguageController's own
 * cookie), strictly whitelists it against LanguageController::LOCALES,
 * and falls back silently to config('app.locale') ('en') for anything
 * missing, tampered, or otherwise invalid — a bad/forged cookie value
 * can never do more than silently behave as if no preference were set.
 *
 * Deliberately reads only the cookie (no DB query, no Settings lookup)
 * so this costs nothing extra on every public request.
 */
class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie(LanguageController::COOKIE_NAME);

        if (in_array($locale, LanguageController::LOCALES, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
