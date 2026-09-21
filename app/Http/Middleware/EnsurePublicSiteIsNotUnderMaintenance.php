<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied only to routes/web.php's public tournament website (see its
 * own Route::middleware() wrapper) — routes/admin.php is a completely
 * separate route file this middleware is never attached to, so an
 * admin can always log in, reach the dashboard, and turn maintenance
 * mode back off even while the public site itself is down. Never
 * `php artisan down`, which would take the whole application (including
 * the admin panel) offline.
 */
class EnsurePublicSiteIsNotUnderMaintenance
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->boolean('system.maintenance_mode')) {
            return $next($request);
        }

        return response()->view('public.maintenance', [
            'message' => $this->settings->get('system.maintenance_message'),
        ], 503);
    }
}
