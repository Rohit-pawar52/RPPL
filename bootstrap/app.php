<?php

use App\Console\Commands\CleanupFailedJobs;
use App\Console\Commands\DispatchMatchReminders;
use App\Console\Commands\DispatchRegistrationClosingReminders;
use App\Console\Commands\DispatchScheduledAnnouncements;
use App\Console\Commands\DispatchTournamentDayReminders;
use App\Http\Middleware\EnsurePasswordIsPrivate;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetAdminLocale;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Admin routes are kept in their own file (separate from the
            // public web routes) but share the standard "web" middleware
            // group (session, CSRF, cookies), then pick the signed-in user's
            // language (English / Hindi) for the whole panel.
            Route::middleware(['web', SetAdminLocale::class])->group(base_path('routes/admin.php'));
        },
    )
    /*
     * The ONE central Laravel Scheduler entry point (Jobs/Queue/
     * Scheduler audit, phase: scheduler foundation). Production needs
     * exactly one OS cron line regardless of how many tasks are
     * registered here — see README's Deployment section. Registrations
     * stay a thin list; all real logic lives in the Commands/Services
     * they invoke, never in this closure.
     *
     * onOneServer() is safe to use even though this app runs
     * single-server today: its locking is backed by config('cache.default'),
     * which is 'database' (CACHE_STORE, .env) — a real shared store, not
     * a per-server local cache — so it remains correct if a second app
     * server is ever added, rather than offering false confidence.
     * withoutOverlapping() additionally protects a single server from a
     * slow run overlapping its own next-minute invocation. Neither is
     * the SOURCE of correctness though — each Command's own database
     * atomic claim (lockForUpdate() + a dispatched_at check) is what
     * actually prevents a duplicate send if both protections somehow
     * failed; see AnnouncementNotificationService/MatchReminderService.
     */
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(DispatchScheduledAnnouncements::class)
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command(DispatchMatchReminders::class)
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command(DispatchRegistrationClosingReminders::class)
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        // Runs every minute; the configured morning time lives in Settings
        // and is checked inside TournamentDayReminderService.
        $schedule->command(DispatchTournamentDayReminders::class)
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        // Day-based retention, so once a day is enough (03:00 in the
        // app timezone). A no-op unless enabled in Settings -> System.
        $schedule->command(CleanupFailedJobs::class)
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // This app has no public-facing "login" route, only admin.login,
        // so the framework's default guest/auth redirect targets (which
        // assume a route named "login") must be pointed at it explicitly.
        // Local only: a dev tunnel (VS Code / Dev Tunnels, ngrok) serves HTTPS and forwards plain HTTP to the
        // local server. Trusting its X-Forwarded-* headers makes forms and redirects use https:// and the
        // tunnel's address instead of http://, which otherwise turns a login POST into a GET and loops back
        // to the login page. Production (Render) gets HTTPS from docker/apache-render.conf and is unchanged.
        if (env('APP_ENV') === 'local') {
            $middleware->trustProxies(at: '*');
        }

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'private-password' => EnsurePasswordIsPrivate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
