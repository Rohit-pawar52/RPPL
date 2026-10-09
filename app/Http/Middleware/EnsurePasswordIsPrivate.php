<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PublicLogins;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A login that still has a PUBLISHED password (see PublicLogins) may do nothing in the admin panel except
 * change that password - or sign out - until it has. Whoever signs in with it, usually the owner who has
 * not got round to setting a real admin yet, lands on the Change password page and cannot go any further.
 *
 * The decision is made from the stored password hash on the request itself, not from a flag set when the
 * login form was submitted, so it also holds for a "remember me" cookie, an old session that was open when
 * a site was updated, and a password that an admin later resets to a published one. To keep it cheap, only
 * the few published emails are ever hashed, and the answer is remembered in the session against the stored
 * hash, so it is worked out once per session and again only when the password changes.
 *
 * It is on in production and off elsewhere (config admin.force_private_password), because on a development
 * machine the demo logins are meant to keep working.
 */
class EnsurePasswordIsPrivate
{
    private const SESSION_KEY = 'public_password_verdict';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('admin.force_private_password')
            || ! $user instanceof User
            || ! PublicLogins::listsEmail($user->email)
            || ! $this->usesPublicPassword($request, $user)) {
            return $next($request);
        }

        // Scripts (live scoring, the auction console) get an answer they can read instead of a page.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'This login still has a publicly known password. Change it first.'], 403);
        }

        return redirect()
            ->route('admin.account.password.edit')
            ->with('warning', 'This login still uses a publicly known password, so nothing else can be opened yet. Choose a new password to continue.');
    }

    private function usesPublicPassword(Request $request, User $user): bool
    {
        // The stored hash, not the password: it changes exactly when the password does.
        $fingerprint = hash('sha256', (string) $user->password);
        $verdict = $request->session()->get(self::SESSION_KEY);

        if (! is_array($verdict) || ($verdict['for'] ?? null) !== $fingerprint) {
            $verdict = ['for' => $fingerprint, 'public' => PublicLogins::usesPublicPassword($user)];
            $request->session()->put(self::SESSION_KEY, $verdict);
        }

        return $verdict['public'];
    }
}
