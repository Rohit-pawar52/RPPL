<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureFirebaseCredentialsFallback();
    }

    /**
     * Throttle admin/scorer login attempts by email + IP to slow down
     * brute-force attempts without a custom locking mechanism.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Generous enough for real guests behind a shared/mobile network
        // (e.g. several family members registering from the same
        // connection) while still bounding abuse — not a CAPTCHA/OTP
        // replacement, just a sane ceiling.
        RateLimiter::for('player-registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Registration numbers are somewhat predictable (sequential,
        // year-prefixed), so the status-lookup endpoint gets its own
        // modest per-IP ceiling — generous enough that a real guest
        // checking a few times from a shared/mobile connection is never
        // blocked, without needing a CAPTCHA/OTP.
        RateLimiter::for('player-registration-status', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Called only after an explicit browser "Enable Notifications"
        // opt-in (Phase B1 audit) — generous enough for a real visitor's
        // own retry/token-refresh, without leaving the endpoint open to
        // bulk abuse.
        RateLimiter::for('fcm-subscribe', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }

    /**
     * Broad, global authorization abilities for the admin application.
     *
     * Gates are used here (not Policies) because these abilities are not
     * tied to a specific model instance — they gate entry into whole
     * areas of the admin app. Per-resource Policies (EditionPolicy,
     * PlayerPolicy, MatchPolicy, ...) will be introduced alongside each
     * module as it is built, not speculatively now.
     */
    private function configureAuthorization(): void
    {
        // Anyone who can authenticate at /admin/login (admin or scorer)
        // may enter the admin shell itself.
        Gate::define('access-admin-panel', function (User $user) {
            return in_array($user->role?->slug, ['admin', 'scorer'], true);
        });

        // Tournament management (editions, teams, players, registrations,
        // squads, venues, reports, settings) is admin-only. Scorers do not
        // automatically receive this ability.
        Gate::define('manage-tournament', function (User $user) {
            return $user->role?->slug === 'admin';
        });
    }

    /**
     * kreait/laravel-firebase's own default config already supports
     * FIREBASE_CREDENTIALS as an env-provided path — but requiring an
     * absolute, machine-specific path in .env is a real portability
     * problem: it works on exactly one developer's machine and breaks
     * the moment the app is deployed anywhere else (a different OS, a
     * different directory, a different developer's checkout).
     *
     * Instead, when FIREBASE_CREDENTIALS is left blank, fall back to
     * storage_path('app/firebase/firebase-service-account.json') —
     * resolved fresh, relative to wherever THIS app instance actually
     * lives, on every machine (dev, staging, production) alike. The
     * credential file only ever needs to exist at that one conventional
     * location; nothing environment-specific goes in .env at all. This
     * runs in boot() (not register()) specifically so it applies AFTER
     * kreait/laravel-firebase's own ServiceProvider has already merged
     * its default config in register() — Laravel guarantees every
     * provider's register() runs before any provider's boot(),
     * regardless of registration order, so this is safe either way.
     * An explicit FIREBASE_CREDENTIALS value, if ever set, still wins
     * (e.g. a hosting platform that mounts the secret somewhere else).
     */
    private function configureFirebaseCredentialsFallback(): void
    {
        if (blank(config('firebase.projects.app.credentials'))) {
            config(['firebase.projects.app.credentials' => storage_path('app/firebase/firebase-service-account.json')]);
        }
    }
}
