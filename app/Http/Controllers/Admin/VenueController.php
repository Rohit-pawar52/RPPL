<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Venue\StoreVenueRequest;
use App\Http\Requests\Admin\Venue\UpdateVenueRequest;
use App\Models\Venue;
use App\Services\Venue\VenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function __construct(private readonly VenueService $venues) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Venue::class);

        $filters = $request->only(['search', 'status']);

        $venues = Venue::query()
            // Deliberately NOT scoped to active() by default: this is the
            // admin management list, which must keep showing both active
            // and inactive venues unless the admin explicitly filters.
            // Venue::active() is reserved for future match-scheduling
            // dropdowns, not this screen.
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%');
                })
            )
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->withCount('matches')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.venues.index', [
            'venues' => $venues,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Venue::class);

        return view('admin.venues.create');
    }

    public function store(StoreVenueRequest $request): RedirectResponse
    {
        $this->authorize('create', Venue::class);

        $this->venues->createVenue($request->validated());

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Venue created successfully.');
    }

    public function show(Venue $venue): View
    {
        $this->authorize('view', $venue);

        $venue->loadCount('matches');
        $venue->load(['matches' => function ($query) {
            $query->with('edition')->latest('scheduled_at')->limit(10);
        }]);

        return view('admin.venues.show', [
            'venue' => $venue,
        ]);
    }

    public function edit(Venue $venue): View
    {
        $this->authorize('update', $venue);

        return view('admin.venues.edit', [
            'venue' => $venue,
        ]);
    }

    public function update(UpdateVenueRequest $request, Venue $venue): RedirectResponse
    {
        $this->authorize('update', $venue);

        $this->venues->updateVenue($venue, $request->validated());

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Venue updated successfully.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $this->authorize('delete', $venue);

        if (! $this->venues->deleteVenue($venue)) {
            return redirect()
                ->route('admin.venues.index')
                ->with('error', 'This venue cannot be deleted because match history exists. Deactivate the venue instead if it should no longer be available.');
        }

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Venue deleted successfully.');
    }
}
