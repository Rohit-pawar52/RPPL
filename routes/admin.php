<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\ContributorController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataCleanupController;
use App\Http\Controllers\Admin\EditionCommitteeMemberController;
use App\Http\Controllers\Admin\EditionContributionController;
use App\Http\Controllers\Admin\EditionController;
use App\Http\Controllers\Admin\EditionTeamController;
use App\Http\Controllers\Admin\EditionTransactionController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\GameMatchController;
use App\Http\Controllers\Admin\InningsController;
use App\Http\Controllers\Admin\MatchFlowController;
use App\Http\Controllers\Admin\MatchPlayerController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PhotoController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\PlayerRegistrationController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\RuleController;
use App\Http\Controllers\Admin\RuleTypeController;
use App\Http\Controllers\Admin\ScorecardController;
use App\Http\Controllers\Admin\ScoringController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamPlayerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Admin\VideoController;
use App\Models\CommitteeMember;
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
        // Must precede the resource route below — otherwise "export"
        // would be captured by the {match} wildcard.
        Route::get('matches/export', [GameMatchController::class, 'export'])->name('matches.export');
        Route::post('matches/export-selected', [GameMatchController::class, 'exportSelected'])->name('matches.export-selected');
        Route::resource('matches', GameMatchController::class);
        // Playing XI (MatchPlayer) is managed contextually from the match
        // show page, not as its own top-level admin module — no sidebar
        // entry, nested under its parent match instead.
        Route::prefix('matches/{match}')->name('matches.')->group(function () {
            Route::get('players', [MatchPlayerController::class, 'index'])->name('players.index');
            // Bulk Playing XI selection — one team, exactly 11 players,
            // one request; replaces the old one-player-at-a-time Add.
            Route::post('players/sync', [MatchPlayerController::class, 'sync'])->name('players.sync');
            Route::patch('players/{matchPlayer}', [MatchPlayerController::class, 'update'])->name('players.update');

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

            // Tied Match / Super Over and Reopen Finalized Match (S02
            // rules 7/17) — explicit actions, distinct from finalize().
            Route::post('super-over', [MatchFlowController::class, 'recordSuperOverResult'])->name('super-over');
            Route::post('reopen', [MatchFlowController::class, 'reopen'])->name('reopen');

            // Manual recovery for the rare case where finalize's own
            // automatic result-notification attempt failed to queue —
            // reuses the same atomic claim, so it's a safe no-op if
            // already dispatched or the match isn't eligible.
            Route::post('resend-result-notification', [MatchFlowController::class, 'resendResultNotification'])->name('resend-result-notification');

            // Innings lifecycle (Phase 3.12) — explicit workflow actions,
            // not Route::resource('innings'): innings identity is derived
            // domain data, never a generic create/edit form.
            Route::post('innings/first/start', [InningsController::class, 'startFirst'])->name('innings.first.start');
            Route::post('innings/{innings}/complete', [InningsController::class, 'complete'])->name('innings.complete');
            Route::post('innings/{innings}/reopen', [InningsController::class, 'reopen'])->name('innings.reopen');
            Route::post('innings/second/start', [InningsController::class, 'startSecond'])->name('innings.second.start');

            // Explicit Start Innings setup (S02 completion rule A) —
            // confirms opening striker/non-striker/bowler; no Delivery.
            Route::post('innings/{innings}/setup', [InningsController::class, 'setupOpeningState'])->name('innings.setup');

            // Ball-by-ball scoring (Phase 3.13) — no generic Delivery
            // resource controller; "undo" is the only correction path,
            // scoped to the latest delivery of this specific innings.
            Route::get('innings/{innings}/score', [ScoringController::class, 'show'])->name('innings.score');
            // Canonical scorer-state JSON (S02 rules 50-61) — polled by
            // the scoring screen's JS and used to recover state after a
            // refresh/reconnect (rule 53).
            Route::get('innings/{innings}/score-data', [ScoringController::class, 'scoreData'])->name('innings.score-data');
            Route::post('innings/{innings}/deliveries', [ScoringController::class, 'store'])->name('innings.deliveries.store');
            // Universal Undo (rule 42) now lives behind this same route —
            // "latest" no longer means only the latest Delivery.
            Route::delete('innings/{innings}/deliveries/latest', [ScoringController::class, 'undoLatest'])->name('innings.deliveries.undo-latest');
            // Quick correction window (rules 43/44/46) — only the latest
            // 3 Delivery rows of an innings are ever eligible; see
            // DeliveryService::correctDelivery().
            Route::patch('innings/{innings}/deliveries/{delivery}/correct', [ScoringController::class, 'correctDelivery'])->name('innings.deliveries.correct');

            // Non-delivery scoring events (S02 rules 4/5/6/20) — never
            // create a Delivery row; each is its own reasoned, audited
            // action rather than a generic "scoring event" endpoint.
            Route::post('innings/{innings}/change-strike', [ScoringController::class, 'changeStrike'])->name('innings.change-strike');
            Route::post('innings/{innings}/retire-batter', [ScoringController::class, 'retireBatter'])->name('innings.retire-batter');
            Route::post('innings/{innings}/penalty-runs', [ScoringController::class, 'awardPenaltyRuns'])->name('innings.penalty-runs');

            // New Batter / New Over Bowler / Mid-Over Bowler Change (S02
            // completion rules C/D/E) — normal-flow continuations (no
            // reason) vs. an explicit, reasoned correction, respectively.
            Route::post('innings/{innings}/select-new-batter', [ScoringController::class, 'selectNewBatter'])->name('innings.select-new-batter');
            Route::post('innings/{innings}/select-over-bowler', [ScoringController::class, 'selectOverBowler'])->name('innings.select-over-bowler');
            Route::post('innings/{innings}/change-bowler', [ScoringController::class, 'changeBowlerMidOver'])->name('innings.change-bowler');

            // Read-only match scorecard (Phase 3.14) — no writes.
            Route::get('scorecard', [ScorecardController::class, 'show'])->name('scorecard');
        });
        // Must precede the resource route below — otherwise "export"
        // would be captured by the {player} wildcard.
        Route::get('players/export', [PlayerController::class, 'export'])->name('players.export');
        Route::resource('players', PlayerController::class);
        // Must precede the resource route below — otherwise "export"/
        // "import" would be captured by the {player_registration} wildcard.
        Route::get('player-registrations/export', [PlayerRegistrationController::class, 'export'])->name('player-registrations.export');
        Route::post('player-registrations/export-selected', [PlayerRegistrationController::class, 'exportSelected'])->name('player-registrations.export-selected');
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
        Route::post('edition-transactions/export-selected', [EditionTransactionController::class, 'exportSelected'])->name('edition-transactions.export-selected');
        Route::resource('edition-transactions', EditionTransactionController::class);

        // Phase 3.48 — Finance is one consolidated admin area (Overview/
        // Contributions/Ledger/Contributors/Committee tabs); "committee
        // member" is no longer a separate identity/CRUD, it is a
        // Contributor's edition-specific membership (see
        // EditionCommitteeMember). committee-members/* below are kept
        // only as compatibility redirects for any old bookmark/link —
        // there is no CommitteeMemberController any more.
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', [FinanceController::class, 'overview'])->name('overview');
            Route::get('committee', [EditionCommitteeMemberController::class, 'index'])->name('committee');
            Route::post('committee', [EditionCommitteeMemberController::class, 'store'])->name('committee.store');
            Route::delete('committee/{edition_committee_member}', [EditionCommitteeMemberController::class, 'destroy'])->name('committee.destroy');
            Route::post('committee/copy-previous', [EditionCommitteeMemberController::class, 'copyPrevious'])->name('committee.copy-previous');
        });
        Route::get('committee-members', fn () => redirect()->route('admin.finance.committee'))->name('committee-members.index');
        Route::get('committee-members/{committee_member}', function (CommitteeMember $committee_member) {
            return $committee_member->contributor
                ? redirect()->route('admin.contributors.show', $committee_member->contributor)
                : redirect()->route('admin.finance.committee');
        })->name('committee-members.show');

        Route::resource('contributors', ContributorController::class);
        // Must precede the resource route below — otherwise "export"
        // would be captured by the {edition_contribution} wildcard.
        Route::get('edition-contributions/export', [EditionContributionController::class, 'export'])->name('edition-contributions.export');
        Route::post('edition-contributions/export-selected', [EditionContributionController::class, 'exportSelected'])->name('edition-contributions.export-selected');
        Route::post('edition-contributions/receipts/selected', [EditionContributionController::class, 'receiptsSelectedPdf'])->name('edition-contributions.receipts.selected');
        Route::get('edition-contributions/dues-preview', [EditionContributionController::class, 'duesPreview'])->name('edition-contributions.dues-preview');
        // No edit/update: a contribution's financial history is never
        // silently rewritten (see EditionContributionController).
        Route::resource('edition-contributions', EditionContributionController::class)->only([
            'index', 'create', 'store', 'show', 'destroy',
        ]);
        // No destroy: accounts are deactivated (is_active), never
        // deleted — see UserController's docblock.
        Route::resource('users', UserController::class)->except(['destroy']);
        // No per-item destroy: a broadcast's content is never deleted
        // individually — see NotificationController's docblock. Bulk,
        // date-based retention cleanup exists separately below
        // (DataCleanupController), never a single-record delete action.
        Route::resource('notifications', NotificationController::class)->except(['destroy']);
        // One explicit action for both Send and Resend (Phase B4) — the
        // backend semantics are identical either way (always the
        // notification's CURRENT content, always a new NotificationSend
        // row); only the button label differs based on send history.
        Route::post('notifications/{notification}/send', [NotificationController::class, 'send'])->name('notifications.send');
        // Bulk retention/cleanup — the ONE centralized destructive-
        // cleanup module (Phase B5, expanded in Phase 3.49 to also
        // cover registration documents and failed queue jobs). Always
        // an explicit, confirmed admin action, never automatic.
        Route::prefix('data-cleanup')->name('data-cleanup.')->group(function () {
            Route::get('/', [DataCleanupController::class, 'index'])->name('index');
            Route::get('preview/cutoff', [DataCleanupController::class, 'previewCutoff'])->name('preview.cutoff');
            Route::get('preview/stale-fcm-tokens', [DataCleanupController::class, 'previewStaleFcmTokens'])->name('preview.stale-fcm-tokens');
            Route::get('preview/registration-documents', [DataCleanupController::class, 'previewRegistrationDocuments'])->name('preview.registration-documents');
            Route::delete('notifications', [DataCleanupController::class, 'destroyNotifications'])->name('notifications.destroy');
            Route::delete('notification-sends', [DataCleanupController::class, 'destroyNotificationSends'])->name('notification-sends.destroy');
            Route::delete('fcm-tokens/inactive', [DataCleanupController::class, 'destroyInactiveFcmTokens'])->name('fcm-tokens.destroy-inactive');
            Route::delete('fcm-tokens/stale', [DataCleanupController::class, 'destroyStaleFcmTokens'])->name('fcm-tokens.destroy-stale');
            Route::delete('registration-documents', [DataCleanupController::class, 'destroyRegistrationDocuments'])->name('registration-documents.destroy');
            Route::delete('failed-jobs', [DataCleanupController::class, 'destroyFailedJobs'])->name('failed-jobs.destroy');
            Route::get('failed-jobs/{uuid}', [DataCleanupController::class, 'failedJobDetail'])->name('failed-jobs.show');
        });
        Route::prefix('edition-contributions/{edition_contribution}')->name('edition-contributions.')->group(function () {
            Route::get('receipt', [EditionContributionController::class, 'receipt'])->name('receipt');
            Route::get('receipt/pdf', [EditionContributionController::class, 'receiptPdf'])->name('receipt.pdf');
        });

        // Global Settings admin UI (Phase 3.44B2) — one tabbed page, one
        // update action per tab so each FormRequest can allow-list only
        // its own tab's fields. Never a single generic "update settings"
        // action that would accept any field from any tab.
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::put('general', [SettingsController::class, 'updateGeneral'])->name('general.update');
            Route::put('contact', [SettingsController::class, 'updateContact'])->name('contact.update');
            Route::put('system', [SettingsController::class, 'updateSystem'])->name('system.update');
            Route::put('payments', [SettingsController::class, 'updatePayments'])->name('payments.update');
            Route::put('public-website', [SettingsController::class, 'updatePublicWebsite'])->name('public-website.update');
        });

        // Public notice-ticker announcements (Phase 3.45) — no show():
        // there's no separate detail view, only manage/edit in place.
        Route::resource('announcements', AnnouncementController::class)->except(['show']);

        // Fixed content pages — Privacy Policy/Terms/FAQs (Phase 3.46).
        // One tabbed index (mirrors Settings) and one update action;
        // no create/store/destroy — the three canonical rows always
        // exist via DemoContentPageSeeder.
        Route::prefix('content-pages')->name('content-pages.')->group(function () {
            Route::get('/', [ContentPageController::class, 'index'])->name('index');
            Route::put('{content_page}', [ContentPageController::class, 'update'])->name('update');
        });

        // Admin-uploaded short RPPL clips for the public homepage's
        // Featured Videos section — no show(), same as announcements.
        // Activate/deactivate is the status field on the edit form, or the
        // one-click toggle from the index table.
        Route::patch('videos/{video}/toggle-status', [VideoController::class, 'toggleStatus'])->name('videos.toggle-status');
        Route::resource('videos', VideoController::class)->except(['show']);

        // News posts (text + optional images) for the public /news pages.
        Route::patch('news/{news}/toggle-status', [NewsController::class, 'toggleStatus'])->name('news.toggle-status');
        Route::resource('news', NewsController::class)->except(['show']);

        // Public photo gallery — same shape as videos.
        Route::patch('photos/{photo}/toggle-status', [PhotoController::class, 'toggleStatus'])->name('photos.toggle-status');
        Route::resource('photos', PhotoController::class)->except(['show']);

        // Rules & Regulations — Rule Types (categories) and the Rules
        // under them. No show(): manage/edit in place, same as videos.
        // A Rule Type that still has Rules is never deleted (guarded in
        // RuleTypeService::deleteType()); deactivate it instead.
        Route::resource('rule-types', RuleTypeController::class)->except(['show']);
        Route::resource('rules', RuleController::class)->except(['show']);
    });
});
