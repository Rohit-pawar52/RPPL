<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Admin\Announcement\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Services\Announcement\AnnouncementNotificationService;
use App\Services\Settings\DisplayTimezoneFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for the public notice ticker's announcements (Phase 3.45).
 * starts_at/ends_at round-trip through DisplayTimezoneFormatter: an
 * admin always sees/enters a time relative to system.display_timezone,
 * never raw UTC, but every persisted value is UTC like every other
 * datetime column — the same convention Phase 3.44B3 established for
 * reading timestamps, extended here to writing them.
 *
 * Scheduled-notification fields (notification_enabled/_scheduled_at)
 * are a SEPARATE concept from starts_at/ends_at — see Announcement's own
 * docblock. "Send Now" and "Schedule for Later" both just set these
 * fields; AnnouncementNotificationService::dispatchIfDue() (called here
 * synchronously for "Send Now" only) and the scheduler-driven
 * DispatchScheduledAnnouncements command are the only two places that
 * ever actually dispatch — this controller never calls
 * NotificationSendService/Firebase directly.
 */
class AnnouncementController extends Controller
{
    public function __construct(
        private readonly DisplayTimezoneFormatter $displayTimezone,
        private readonly AnnouncementNotificationService $notifications,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::query()
            ->with(['creator:id,name', 'notification'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(20);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Announcement::class);

        return view('admin.announcements.create');
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $data = $request->validated();
        $choice = $data['notification_choice'] ?? 'none';

        $announcement = Announcement::create([
            'message' => $data['message'],
            'starts_at' => $this->displayTimezone->parseFromDisplayTimezone($data['starts_at'] ?? null),
            'ends_at' => $this->displayTimezone->parseFromDisplayTimezone($data['ends_at'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $request->user()->id,
            'notification_enabled' => $choice !== 'none',
            'notification_scheduled_at' => $choice === 'later'
                ? $this->displayTimezone->parseFromDisplayTimezone($data['notification_scheduled_at'])
                : null,
        ]);

        $message = 'Announcement created successfully.';

        if ($choice === 'now') {
            // Best-effort: a null scheduled_at is always "due", so even
            // if this attempt fails (e.g. a transient queue-connection
            // issue), the next scheduler pass (within a minute) retries
            // it automatically — see AnnouncementNotificationService.
            $message .= $this->notifications->dispatchIfDue($announcement->id)
                ? ' Push notification queued.'
                : ' Push notification will be sent shortly.';
        } elseif ($choice === 'later') {
            $message .= ' Push notification scheduled.';
        }

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', $message);
    }

    public function edit(Announcement $announcement): View
    {
        $this->authorize('update', $announcement);

        return view('admin.announcements.edit', [
            'announcement' => $announcement,
        ]);
    }

    /**
     * created_by is never part of $data here — authorship is set once,
     * at creation, the same convention NotificationController already
     * established.
     *
     * notification_enabled/_scheduled_at are only ever written here
     * while notification_dispatched_at is still null. Once a
     * notification has actually been dispatched, submitted changes to
     * those two fields are silently ignored (never an error) — editing
     * an announcement's content/ticker window afterwards must never
     * resend or reschedule a push that already fired. A fresh push
     * later is a deliberate future/admin-explicit feature, not a side
     * effect of an ordinary content edit.
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $data = $request->validated();
        $alreadyDispatched = $announcement->notification_dispatched_at !== null;

        $updates = [
            'message' => $data['message'],
            'starts_at' => $this->displayTimezone->parseFromDisplayTimezone($data['starts_at'] ?? null),
            'ends_at' => $this->displayTimezone->parseFromDisplayTimezone($data['ends_at'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];

        $choice = $data['notification_choice'] ?? 'none';

        if (! $alreadyDispatched) {
            $updates['notification_enabled'] = $choice !== 'none';
            $updates['notification_scheduled_at'] = $choice === 'later'
                ? $this->displayTimezone->parseFromDisplayTimezone($data['notification_scheduled_at'])
                : null;
        }

        $announcement->update($updates);

        $message = 'Announcement updated successfully.';

        if (! $alreadyDispatched && $choice === 'now') {
            $message .= $this->notifications->dispatchIfDue($announcement->id)
                ? ' Push notification queued.'
                : ' Push notification will be sent shortly.';
        }

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', $message);
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement deleted successfully.');
    }
}
