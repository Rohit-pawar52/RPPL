<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Admin\Announcement\UpdateAnnouncementRequest;
use App\Models\Announcement;
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
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly DisplayTimezoneFormatter $displayTimezone) {}

    public function index(): View
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::query()
            ->with('creator:id,name')
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

        Announcement::create([
            'message' => $data['message'],
            'starts_at' => $this->displayTimezone->parseFromDisplayTimezone($data['starts_at'] ?? null),
            'ends_at' => $this->displayTimezone->parseFromDisplayTimezone($data['ends_at'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement created successfully.');
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
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $data = $request->validated();

        $announcement->update([
            'message' => $data['message'],
            'starts_at' => $this->displayTimezone->parseFromDisplayTimezone($data['starts_at'] ?? null),
            'ends_at' => $this->displayTimezone->parseFromDisplayTimezone($data['ends_at'] ?? null),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement updated successfully.');
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
