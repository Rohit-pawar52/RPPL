<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\StoreNotificationRequest;
use App\Http\Requests\Admin\Notification\UpdateNotificationRequest;
use App\Models\FcmToken;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin notification-CONTENT management (Phase B3) — plain CRUD for the
 * editable Notification master. No Send/Resend action exists yet (a
 * later phase) and this deliberately has no destroy(): there is no real
 * business need to delete a broadcast's content once authored, the same
 * reasoning UserController's account-deactivation-not-deletion
 * precedent already established for this project.
 *
 * Never contains Firebase/queue logic — that belongs entirely to the
 * future sending phase.
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Notification::class);

        // sends() is eager-loaded in full (not per-row limited/queried)
        // so "Send Count"/"Last Sent" below can be computed in memory
        // from the already-loaded collection — the standard, N+1-safe
        // way to eager-load a small hasMany, without needing a second
        // withCount() query or a more complex latestOfMany() relation
        // that this admin list doesn't otherwise need.
        $notifications = Notification::query()
            ->with(['creator:id,name', 'sends'])
            ->latest('id')
            ->paginate(15);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'activeSubscriberCount' => FcmToken::active()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Notification::class);

        return view('admin.notifications.create');
    }

    public function store(StoreNotificationRequest $request): RedirectResponse
    {
        $this->authorize('create', Notification::class);

        Notification::create([
            'title' => $request->validated('title'),
            'message' => $request->validated('message'),
            'action_url' => $request->validated('action_url'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification created successfully.');
    }

    public function show(Notification $notification): View
    {
        $this->authorize('view', $notification);

        $notification->load([
            'creator:id,name',
            'sends' => fn ($query) => $query->with('sender:id,name')->latest('id'),
        ]);

        return view('admin.notifications.show', [
            'notification' => $notification,
        ]);
    }

    public function edit(Notification $notification): View
    {
        $this->authorize('update', $notification);

        return view('admin.notifications.edit', [
            'notification' => $notification,
        ]);
    }

    /**
     * created_by is never part of $data here — a notification's
     * authorship is immutable after creation (see the class docblock).
     */
    public function update(UpdateNotificationRequest $request, Notification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);

        $notification->update([
            'title' => $request->validated('title'),
            'message' => $request->validated('message'),
            'action_url' => $request->validated('action_url'),
        ]);

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification updated successfully.');
    }
}
