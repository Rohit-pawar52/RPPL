<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CommitteeMemberController;
use App\Http\Controllers\Admin\ContributorController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EditionContributionController;
use App\Http\Controllers\Admin\EditionController;
use App\Http\Controllers\Admin\EditionTeamController;
use App\Http\Controllers\Admin\EditionTransactionController;
use App\Http\Controllers\Admin\GameMatchController;
use App\Http\Controllers\Admin\InningsController;
use App\Http\Controllers\Admin\MatchFlowController;
use App\Http\Controllers\Admin\MatchPlayerController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\PlayerRegistrationController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\ScorecardController;
use App\Http\Controllers\Admin\ScoringController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamPlayerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VenueController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login.store');
    });

    // "active" runs right after "auth" (is this session even still allowed
    // to exist) and before "can:access-admin-panel" (what is it allowed to
    // do) — authentication status and authorization role are deliberately
    // kept as separate checks.
    Route::middleware(['auth', 'active', 'can:access-admin-panel'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Read-side reporting/navigation hub (Phase 3.43) — links to
        // existing exports/PDFs, plus one new print-friendly Financial
        // Summary. No writes, no new tables.
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportsController::class, 'index'])->name('index');
            Route::get('/financial-summary', [ReportsController::class, 'financialSummary'])->name('financial-summary');
        });

        // EditionController and PlayerController additionally enforce their
        // policies (admin-only) via explicit $this->authorize() calls in
        // each controller method (authorizeResource() is incompatible with
        // this project's minimal Controller base class — see Phase 3.2).
        Route::resource('editions', EditionController::class);
        Route::get('editions/{edition}/report/pdf', [EditionController::class, 'reportPdf'])->name('editions.report.pdf');
        // EditionTeam has no editable attributes beyond its own identity
        // (edition_id, team_id) — create/view/delete only, no edit/update.
        Route::resource('edition-teams', EditionTeamController::class)->only([
            'index', 'create', 'store', 'show', 'destroy',
        ]);
        Route::resource('matches', GameMatchController::class);
        // Playing XI (MatchPlayer) is managed contextually from the match
        // show page, not as its own top-level admin module — no sidebar
        // entry, nested under its parent match instead.
        Route::prefix('matches/{match}')->name('matches.')->group(function () {
            Route::get('players', [MatchPlayerController::class, 'index'])->name('players.index');
            Route::post('players', [MatchPlayerController::class, 'store'])->name('players.store');
            Route::patch('players/{matchPlayer}', [MatchPlayerController::class, 'update'])->name('players.update');
            Route::delete('players/{matchPlayer}', [MatchPlayerController::class, 'destroy'])->name('players.destroy');

            // Match-day setup workflow (Phase 3.11) — explicit contextual
            // actions rather than a generic match_status update, so a
            // client can never drive an arbitrary status transition.
            Route::post('start-toss', [MatchFlowController::class, 'startToss'])->name('start-toss');
            Route::put('toss', [MatchFlowController::class, 'recordToss'])->name('toss.update');
            Route::post('start', [MatchFlowController::class, 'startMatch'])->name('start');

            // Lifecycle termination (Phase 3.23) — explicit actions, not
            // a generic status update, so neither can drive an arbitrary
            // match_status transition from the client.
            Route::post('cancel', [MatchFlowController::class, 'cancel'])->name('cancel');
            Route::post('abandon', [MatchFlowController::class, 'abandon'])->name('abandon');

            // Result finalization (Phase 3.15) — explicit action, no
            // form fields; the result is always server-derived from
            // completed innings totals, never chosen by the admin/scorer.
            Route::post('finalize', [MatchFlowController::class, 'finalize'])->name('finalize');

            // Innings lifecycle (Phase 3.12) — explicit workflow actions,
            // not Route::resource('innings'): innings identity is derived
            // domain data, never a generic create/edit form.
            Route::post('innings/first/start', [InningsController::class, 'startFirst'])->name('innings.first.start');
            Route::post('innings/{innings}/complete', [InningsController::class, 'complete'])->name('innings.complete');
            Route::post('innings/second/start', [InningsController::class, 'startSecond'])->name('innings.second.start');

            // Ball-by-ball scoring (Phase 3.13) — no generic Delivery
            // resource controller; "undo" is the only correction path,
            // scoped to the latest delivery of this specific innings.
            Route::get('innings/{innings}/score', [ScoringController::class, 'show'])->name('innings.score');
            Route::post('innings/{innings}/deliveries', [ScoringController::class, 'store'])->name('innings.deliveries.store');
            Route::delete('innings/{innings}/deliveries/latest', [ScoringController::class, 'undoLatest'])->name('innings.deliveries.undo-latest');

            // Read-only match scorecard (Phase 3.14) — no writes.
            Route::get('scorecard', [ScorecardController::class, 'show'])->name('scorecard');
        });
        Route::resource('players', PlayerController::class);
        // Must precede the resource route below — otherwise "export"/
        // "import" would be captured by the {player_registration} wildcard.
        Route::get('player-registrations/export', [PlayerRegistrationController::class, 'export'])->name('player-registrations.export');
        Route::get('player-registrations/import', [PlayerRegistrationController::class, 'import'])->name('player-registrations.import');
        Route::post('player-registrations/import', [PlayerRegistrationController::class, 'importStore'])->name('player-registrations.import.store');
        // Private document review (Phase 3.39D) — served through the
        // controller after policy authorization, never via Storage::url()
        // or a public disk; the model route binding means only a
        // path column already stored on THIS registration can ever be
        // read, never one supplied by the request.
        Route::get('player-registrations/{player_registration}/aadhaar', [PlayerRegistrationController::class, 'aadhaar'])->name('player-registrations.aadhaar');
        Route::get('player-registrations/{player_registration}/payment-proof', [PlayerRegistrationController::class, 'paymentProof'])->name('player-registrations.payment-proof');
        Route::resource('player-registrations', PlayerRegistrationController::class);
        Route::resource('teams', TeamController::class);
        Route::resource('team-players', TeamPlayerController::class);
        Route::resource('venues', VenueController::class);
        // Must precede the resource route below — otherwise "export"
        // would be captured by the {edition_transaction} wildcard.
        Route::get('edition-transactions/export', [EditionTransactionController::class, 'export'])->name('edition-transactions.export');
        Route::resource('edition-transactions', EditionTransactionController::class);
        Route::resource('committee-members', CommitteeMemberController::class);
        Route::resource('contributors', ContributorController::class);
        // Must precede the resource route below — otherwise "export"
        // would be captured by the {edition_contribution} wildcard.
        Route::get('edition-contributions/export', [EditionContributionController::class, 'export'])->name('edition-contributions.export');
        // No edit/update: a contribution's financial history is never
        // silently rewritten (see EditionContributionController).
        Route::resource('edition-contributions', EditionContributionController::class)->only([
            'index', 'create', 'store', 'show', 'destroy',
        ]);
        // No destroy: accounts are deactivated (is_active), never
        // deleted — see UserController's docblock.
        Route::resource('users', UserController::class)->except(['destroy']);
        // No destroy: a broadcast's content is never deleted once
        // authored — see NotificationController's docblock.
        Route::resource('notifications', NotificationController::class)->except(['destroy']);
        // One explicit action for both Send and Resend (Phase B4) — the
        // backend semantics are identical either way (always the
        // notification's CURRENT content, always a new NotificationSend
        // row); only the button label differs based on send history.
        Route::post('notifications/{notification}/send', [NotificationController::class, 'send'])->name('notifications.send');
        Route::prefix('edition-contributions/{edition_contribution}')->name('edition-contributions.')->group(function () {
            Route::get('receipt', [EditionContributionController::class, 'receipt'])->name('receipt');
            Route::get('receipt/pdf', [EditionContributionController::class, 'receiptPdf'])->name('receipt.pdf');
        });
    });
});
