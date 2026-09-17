<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\StoreTeamRequest;
use App\Http\Requests\Admin\Team\UpdateTeamRequest;
use App\Models\Team;
use App\Services\Team\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Team::class);

        $filters = $request->only(['search', 'status']);

        $teams = Team::query()
            // Deliberately NOT scoped to active() by default: this is the
            // admin management list, which must keep showing both active
            // and inactive teams unless the admin explicitly filters.
            // Team::active() is reserved for future selection dropdowns
            // (e.g. EditionTeam assignment), not this screen.
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%')
            )
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->withCount('editionTeams')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.teams.index', [
            'teams' => $teams,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Team::class);

        return view('admin.teams.create');
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        $this->teams->createTeam($request->safe()->except('logo'), $request->file('logo'));

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team): View
    {
        $this->authorize('view', $team);

        $team->loadCount('editionTeams');
        $team->load(['editionTeams' => function ($query) {
            $query->with('edition')->latest('id');
        }]);

        return view('admin.teams.show', [
            'team' => $team,
        ]);
    }

    public function edit(Team $team): View
    {
        $this->authorize('update', $team);

        return view('admin.teams.edit', [
            'team' => $team,
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $this->teams->updateTeam($team, $request->safe()->except('logo'), $request->file('logo'));

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        if (! $this->teams->deleteTeam($team)) {
            return redirect()
                ->route('admin.teams.index')
                ->with('error', 'This team cannot be deleted because tournament history exists. Deactivate the team instead if it should no longer be available.');
        }

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted successfully.');
    }
}
