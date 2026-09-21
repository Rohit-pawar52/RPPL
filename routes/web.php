<?php

use App\Http\Controllers\Public\EditionController;
use App\Http\Controllers\Public\FcmTokenController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MatchController;
use App\Http\Controllers\Public\PlayerController;
use App\Http\Controllers\Public\PlayerRegistrationController;
use App\Http\Controllers\Public\TeamController;
use App\Http\Controllers\Public\VenueController;
use App\Http\Middleware\EnsurePublicSiteIsNotUnderMaintenance;
use Illuminate\Support\Facades\Route;

// Public tournament website — read-only, no auth/policy middleware.
// Admin routes live entirely in routes/admin.php, kept separate, and
// are never wrapped in EnsurePublicSiteIsNotUnderMaintenance — an admin
// must always be able to log in and turn maintenance mode back off.
// Every public route (including guest registration and the FCM
// subscribe endpoint) is in scope for maintenance mode; no route here
// is exempted without a concrete operational reason (see Phase 3.44B3).
Route::middleware(EnsurePublicSiteIsNotUnderMaintenance::class)->group(function () {
    Route::get('/', HomeController::class)->name('public.home');

    Route::prefix('editions')->name('public.editions.')->group(function () {
        Route::get('/', [EditionController::class, 'index'])->name('index');
        Route::get('/{edition}', [EditionController::class, 'show'])->name('show');
    });

    Route::prefix('matches')->name('public.matches.')->group(function () {
        Route::get('/', [MatchController::class, 'index'])->name('index');
        Route::get('/{match}', [MatchController::class, 'show'])->name('show');
        Route::get('/{match}/scorecard', [MatchController::class, 'scorecard'])->name('scorecard');
        Route::get('/{match}/scorecard/pdf', [MatchController::class, 'scorecardPdf'])->name('scorecard.pdf');
        Route::get('/{match}/live', [MatchController::class, 'live'])->name('live');
        Route::get('/{match}/live-data', [MatchController::class, 'liveData'])->name('live-data');
    });

    Route::prefix('players')->name('public.players.')->group(function () {
        Route::get('/', [PlayerController::class, 'index'])->name('index');
        Route::get('/{player}', [PlayerController::class, 'show'])->name('show');
    });

    Route::prefix('teams')->name('public.teams.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::get('/{team}', [TeamController::class, 'show'])->name('show');
    });

    Route::prefix('venues')->name('public.venues.')->group(function () {
        Route::get('/', [VenueController::class, 'index'])->name('index');
        Route::get('/{venue}', [VenueController::class, 'show'])->name('show');
    });

    Route::prefix('player-registration')->name('public.player-registration.')->group(function () {
        Route::get('/', [PlayerRegistrationController::class, 'create'])->name('create');
        Route::post('/', [PlayerRegistrationController::class, 'store'])
            ->middleware('throttle:player-registration')
            ->name('store');
        Route::get('/success', [PlayerRegistrationController::class, 'success'])->name('success');

        // Public/guest registration-status lookup (Phase 3.39E) — historical,
        // always available regardless of whether registration is currently
        // open. Never a GET /player-registration/{registration_number}
        // resource route: the phone credential must never appear in a URL.
        Route::get('/status', [PlayerRegistrationController::class, 'status'])->name('status');
        Route::post('/status', [PlayerRegistrationController::class, 'statusLookup'])
            ->middleware('throttle:player-registration-status')
            ->name('status.lookup');
    });

    // Anonymous FCM token subscription (Phase B1) — no login, no browser/
    // Firebase integration yet; this is the data-foundation endpoint only.
    Route::prefix('notifications')->name('public.notifications.')->group(function () {
        Route::post('/subscribe', [FcmTokenController::class, 'store'])
            ->middleware('throttle:fcm-subscribe')
            ->name('subscribe');
    });
});
