<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocking new logins for an inactive account (LoginRequest) is not
 * enough on its own: a session that was already authenticated before
 * the account was deactivated would otherwise keep working. This
 * middleware re-checks is_active on every request to a protected admin
 * route, since the session guard re-fetches the user from the database
 * each time — so a deactivation takes effect on the very next request,
 * not just at the next login attempt.
 *
 * This is an authentication concern (is this session still allowed to
 * exist at all), deliberately separate from authorization (Gates and
 * Policies decide what an active session is allowed to do). It runs
 * after "auth" and before "can:access-admin-panel" in the route group.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
