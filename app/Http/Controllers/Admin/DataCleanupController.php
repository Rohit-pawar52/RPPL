<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DataCleanup\DeleteFailedJobsRequest;
use App\Http\Requests\Admin\DataCleanup\DeleteNotificationSendsRequest;
use App\Http\Requests\Admin\DataCleanup\DeleteNotificationsRequest;
use App\Http\Requests\Admin\DataCleanup\DeleteRegistrationDocumentsRequest;
use App\Http\Requests\Admin\DataCleanup\DeleteStaleFcmTokensRequest;
use App\Models\Edition;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Services\DataCleanup\DataCleanupLogger;
use App\Services\DataCleanup\FailedJobCleanupService;
use App\Services\DataCleanup\RegistrationDocumentCleanupService;
use App\Services\Notification\NotificationDataCleanupService;
use App\Services\Settings\DisplayTimezoneFormatter;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin-triggered bulk retention/cleanup — the ONE centralized place
 * for destructive data-retention actions across the app (Phase 3.49
 * expanded this from notifications-only to also cover registration
 * documents and failed queue jobs). Never runs automatically, always
 * an explicit click with an explicit confirmation and a server-
 * calculated preview count first. Gated behind the existing
 * "manage-tournament" Gate, the same broad admin-only check
 * ReportsController uses for a non-resource, non-Policy page.
 *
 * Three tabs (?tab=notifications|registration-documents|system),
 * mirroring the Settings module's own ?tab= pattern exactly — each tab
 * is its own scoped set of actions, never one generic deleteData($type)
 * endpoint.
 */
class DataCleanupController extends Controller
{
    private const TABS = ['notifications', 'registration-documents', 'system'];

    public function __construct(
        private readonly NotificationDataCleanupService $notifications,
        private readonly RegistrationDocumentCleanupService $documents,
        private readonly FailedJobCleanupService $failedJobs,
        private readonly DataCleanupLogger $logger,
        private readonly DisplayTimezoneFormatter $timezoneFormatter,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-tournament');

        $tab = $request->query('tab', 'notifications');

        if (! in_array($tab, self::TABS, true)) {
            $tab = 'notifications';
        }

        return view('admin.data-cleanup.index', [
            'activeTab' => $tab,
            'displayTimezone' => $this->settings->get('system.display_timezone'),
            'notificationCount' => Notification::count(),
            'notificationSendCount' => NotificationSend::count(),
            'fcmTokenCount' => FcmToken::count(),
            'inactiveFcmTokenCount' => FcmToken::where('is_active', false)->count(),
            'staleDaysOptions' => DeleteStaleFcmTokensRequest::DAYS_OPTIONS,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name', 'year', 'registration_open']),
            'documentTypes' => RegistrationDocumentCleanupService::TYPES,
            'failedJobCount' => DB::table('failed_jobs')->count(),
        ]);
    }

    /**
     * Read-only server-calculated count for a date-cutoff category —
     * backs the live preview on the Notifications/System tabs before an
     * admin confirms. Same viewAny-equivalent admin-only gate as every
     * destructive action here; a preview is never treated as harmless
     * just because it doesn't delete anything.
     */
    public function previewCutoff(Request $request): JsonResponse
    {
        $this->authorize('manage-tournament');

        $validated = $request->validate([
            'category' => ['required', Rule::in(['notifications', 'notification-sends', 'failed-jobs'])],
            'before_date' => ['required', 'date'],
        ]);

        $before = $this->timezoneFormatter->startOfDisplayDate($validated['before_date']);

        $count = match ($validated['category']) {
            'notifications' => $this->notifications->countNotificationsBefore($before),
            'notification-sends' => $this->notifications->countNotificationSendsBefore($before),
            'failed-jobs' => $this->failedJobs->countFailedBefore($before),
        };

        return response()->json(['count' => $count]);
    }

    /**
     * Read-only server-calculated preview for the stale-FCM-token
     * days threshold (a <select>, not a date picker, so it doesn't fit
     * previewCutoff()'s date-based contract above).
     */
    public function previewStaleFcmTokens(Request $request): JsonResponse
    {
        $this->authorize('manage-tournament');

        $validated = $request->validate([
            'days' => ['required', 'integer', Rule::in(DeleteStaleFcmTokensRequest::DAYS_OPTIONS)],
        ]);

        $count = $this->notifications->countStaleFcmTokens(now()->subDays($validated['days']));

        return response()->json(['count' => $count]);
    }

    /**
     * Read-only server-calculated preview for the Registration
     * Documents tab.
     */
    public function previewRegistrationDocuments(Request $request): JsonResponse
    {
        $this->authorize('manage-tournament');

        $validated = $request->validate([
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'document_type' => ['required', Rule::in(RegistrationDocumentCleanupService::TYPES)],
        ]);

        $edition = Edition::findOrFail($validated['edition_id']);
        $counts = $this->documents->previewCounts($edition, $validated['document_type']);

        return response()->json($counts);
    }

    public function destroyNotifications(DeleteNotificationsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $beforeDate = $request->validated('before_date');
        $before = $this->timezoneFormatter->startOfDisplayDate($beforeDate);

        $deleted = $this->notifications->deleteNotificationsBefore($before);

        $this->logger->log(
            $request->user(),
            'notifications',
            'delete_notifications_before',
            ['before_date' => $beforeDate, 'timezone' => $this->settings->get('system.display_timezone')],
            $deleted,
        );

        return redirect()
            ->route('admin.data-cleanup.index', ['tab' => 'notifications'])
            ->with('success', "Deleted {$deleted} notification(s) and their send history.");
    }

    public function destroyNotificationSends(DeleteNotificationSendsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $beforeDate = $request->validated('before_date');
        $before = $this->timezoneFormatter->startOfDisplayDate($beforeDate);

        $deleted = $this->notifications->deleteNotificationSendsBefore($before);

        $this->logger->log(
            $request->user(),
            'notification_sends',
            'delete_notification_sends_before',
            ['before_date' => $beforeDate, 'timezone' => $this->settings->get('system.display_timezone')],
            $deleted,
        );

        return redirect()
            ->route('admin.data-cleanup.index', ['tab' => 'notifications'])
            ->with('success', "Deleted {$deleted} notification send record(s).");
    }

    public function destroyInactiveFcmTokens(Request $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $deleted = $this->notifications->deleteInactiveFcmTokens();

        $this->logger->log($request->user(), 'fcm_tokens', 'delete_inactive_fcm_tokens', [], $deleted);

        return redirect()
            ->route('admin.data-cleanup.index', ['tab' => 'notifications'])
            ->with('success', "Deleted {$deleted} inactive FCM token(s).");
    }

    public function destroyStaleFcmTokens(DeleteStaleFcmTokensRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $days = $request->validated('days');
        $before = now()->subDays($days);

        $deleted = $this->notifications->deleteStaleFcmTokens($before);

        $this->logger->log($request->user(), 'fcm_tokens', 'delete_stale_fcm_tokens', ['days' => $days], $deleted);

        return redirect()
            ->route('admin.data-cleanup.index', ['tab' => 'notifications'])
            ->with('success', "Deleted {$deleted} FCM token(s) not seen in the last {$days} days.");
    }

    public function destroyRegistrationDocuments(DeleteRegistrationDocumentsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $edition = Edition::findOrFail($request->validated('edition_id'));
        $documentType = $request->validated('document_type');

        $result = $this->documents->deleteDocuments($edition, $documentType);

        $this->logger->log(
            $request->user(),
            'registration_documents',
            'delete_registration_documents',
            ['edition_id' => $edition->id, 'document_type' => $documentType],
            $result['registrations_updated'],
            $result['files_deleted'],
        );

        $message = "{$result['registrations_updated']} registration(s) updated, {$result['files_deleted']} file(s) deleted";

        if ($result['files_missing'] > 0) {
            $message .= " ({$result['files_missing']} database reference(s) already pointed to missing files)";
        }

        return redirect()
            ->route('admin.data-cleanup.index', ['tab' => 'registration-documents'])
            ->with('success', $message.'.');
    }

    public function destroyFailedJobs(DeleteFailedJobsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $beforeDate = $request->validated('before_date');
        $before = $this->timezoneFormatter->startOfDisplayDate($beforeDate);

        $deleted = $this->failedJobs->deleteFailedBefore($before);

        $this->logger->log(
            $request->user(),
            'failed_jobs',
            'delete_failed_jobs_before',
            ['before_date' => $beforeDate, 'timezone' => $this->settings->get('system.display_timezone')],
            $deleted,
        );

        return redirect()
            ->route('admin.data-cleanup.index', ['tab' => 'system'])
            ->with('success', "Deleted {$deleted} failed job record(s).");
    }
}
