<?php

namespace App\Providers;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\User;
use App\Services\Advertisement\AdvertisementDisplayService;
use App\Services\Auction\AuctionChangeAnnouncer;
use App\View\Composers\AnnouncementTickerComposer;
use App\View\Composers\BrandingComposer;
use App\View\Composers\ContentPageFooterComposer;
use App\View\Composers\PublicNavComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request: the sponsor ads are read once and the rotating
        // banner stays the same everywhere it appears on that page.
        $this->app->singleton(AdvertisementDisplayService::class);

        // One announcer per request, so a burst of saves inside one auction
        // action is announced once.
        $this->app->singleton(AuctionChangeAnnouncer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthorization();
        $this->configureFirebaseCredentialsFallback();
        $this->configureBranding();
        $this->configureAnnouncementTicker();
        $this->configureContentPageFooter();
        $this->configurePublicNav();
        $this->configureAuctionAnnouncements();
    }

    /**
     * Any change to an auction, a player in it or a bid on one tells the
     * public page (cache dropped, Reverb signal) once the change is
     * committed — see AuctionChangeAnnouncer.
     */
    private function configureAuctionAnnouncements(): void
    {
        $announce = fn ($model) => $this->app->make(AuctionChangeAnnouncer::class)->changed($model);

        foreach ([Auction::class, AuctionLot::class, AuctionBid::class] as $model) {
            $model::saved($announce);
            $model::deleted($announce);
        }
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
        // (a whole team registering together from one connection, or
        // someone retrying after a rejected upload — every attempt counts,
        // not only the successful ones) while still bounding abuse — not a
        // CAPTCHA/OTP replacement, just a sane ceiling.
        RateLimiter::for('player-registration', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
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
        // Anyone who can authenticate at /admin/login (admin, scorer or
        // auctioneer) may enter the admin shell itself. What each of them
        // can then open is decided by the policies and the two role gates
        // below.
        Gate::define('access-admin-panel', function (User $user) {
            return in_array($user->role?->slug, ['admin', 'scorer', 'auctioneer'], true);
        });

        // Running matches: scoring, toss, playing XI. Admin and scorer.
        Gate::define('score-matches', function (User $user) {
            return in_array($user->role?->slug, ['admin', 'scorer'], true);
        });

        // Running the player auction. Admin and auctioneer — an auctioneer
        // gets nothing outside the auction.
        Gate::define('run-auction', function (User $user) {
            return in_array($user->role?->slug, ['admin', 'auctioneer'], true);
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

    /**
     * Shares one $branding value object (App\Support\Branding) with the
     * views that render presentation settings (Phase 3.44B3; widened in
     * the pre-UAT stabilization pass to 'public.*' so every public child
     * view — not just the handful originally listed — can reference
     * $branding in its own @section('title', ...), since a child view's
     * sections execute in the child's own data scope before the parent
     * layout's composer fires). 'public.*' already covers public.home,
     * public.maintenance and public.content-page; those are kept
     * explicit anyway since composer registration is idempotent.
     * Layout files pass $branding forward to their own @include'd
     * partials automatically (Blade's normal scope inheritance), so
     * partials are not listed here separately.
     */
    private function configureBranding(): void
    {
        View::composer([
            'layouts.public',
            'layouts.admin',
            'layouts.guest',
            'public.*',
            'public.home',
            'public.maintenance',
            'public.content-page',
            'admin.editions.report-pdf',
            'admin.edition-contributions.receipt',
            'public.matches.scorecard-pdf',
        ], BrandingComposer::class);
    }

    /**
     * The public ticker's currently-active announcements (Phase 3.45) —
     * bound only to its own partial view, included exactly once from
     * layouts.public, so this query never runs for admin/guest/
     * maintenance pages (none of which include that partial).
     */
    private function configureAnnouncementTicker(): void
    {
        View::composer('layouts.partials.announcement-ticker', AnnouncementTickerComposer::class);
    }

    /**
     * The public footer's active content-page links (Phase 3.46) —
     * bound only to its own partial view, so this never runs for
     * admin/guest/maintenance pages (none of which include the public
     * footer).
     */
    private function configureContentPageFooter(): void
    {
        View::composer('layouts.partials.public-footer', ContentPageFooterComposer::class);
    }

    /**
     * The public header's "current edition" for the Points Table nav
     * link — bound only to its own partial, so this never runs for
     * admin/guest/maintenance pages (none of which include it).
     */
    private function configurePublicNav(): void
    {
        View::composer('layouts.partials.public-header', PublicNavComposer::class);
    }
}
