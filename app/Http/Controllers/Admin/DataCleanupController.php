<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DataCleanup\DeleteFcmTokensRequest;
use App\Http\Requests\Admin\DataCleanup\DeleteNotificationSendsRequest;
use App\Http\Requests\Admin\DataCleanup\DeleteNotificationsRequest;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\NotificationSend;
use App\Services\Notification\NotificationDataCleanupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin-triggered bulk retention/cleanup for the notification-domain
 * tables (Phase B5) — never runs automatically, always an explicit
 * click with an explicit confirmation. Gated behind the existing
 * "manage-tournament" Gate, the same broad admin-only check
 * ReportsController uses for a non-resource, non-Policy page.
 */
class DataCleanupController extends Controller
{
    public function __construct(private readonly NotificationDataCleanupService $cleanup) {}

    public function index(): View
    {
        $this->authorize('manage-tournament');

        return view('admin.data-cleanup.index', [
            'notificationCount' => Notification::count(),
            'notificationSendCount' => NotificationSend::count(),
            'fcmTokenCount' => FcmToken::count(),
            'activeFcmTokenCount' => FcmToken::active()->count(),
            'keepCountOptions' => DeleteFcmTokensRequest::KEEP_COUNT_OPTIONS,
        ]);
    }

    public function destroyNotifications(DeleteNotificationsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $deleted = $this->cleanup->deleteNotificationsBefore(
            Carbon::parse($request->validated('before_date'))->startOfDay(),
        );

        return redirect()
            ->route('admin.data-cleanup.index')
            ->with('success', "Deleted {$deleted} notification(s) and their send history.");
    }

    public function destroyNotificationSends(DeleteNotificationSendsRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $deleted = $this->cleanup->deleteNotificationSendsBefore(
            Carbon::parse($request->validated('before_date'))->startOfDay(),
        );

        return redirect()
            ->route('admin.data-cleanup.index')
            ->with('success', "Deleted {$deleted} notification send record(s).");
    }

    public function destroyFcmTokens(DeleteFcmTokensRequest $request): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $deleted = $this->cleanup->keepLatestFcmTokens($request->validated('keep_count'));

        return redirect()
            ->route('admin.data-cleanup.index')
            ->with('success', "Deleted {$deleted} FCM token(s), keeping the {$request->validated('keep_count')} most recently active.");
    }
}
