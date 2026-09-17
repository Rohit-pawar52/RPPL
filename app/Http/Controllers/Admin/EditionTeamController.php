<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditionTeam\StoreEditionTeamRequest;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use App\Services\EditionTeam\EditionTeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditionTeamController extends Controller
{
    public function __construct(private readonly EditionTeamService $editionTeams) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EditionTeam::class);

        $filters = $request->only(['search', 'edition_id']);

        $editionTeams = EditionTeam::query()
            ->with(['edition', 'team'])
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->whereHas('team', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%');
                })
            )
            ->when(
                $filters['edition_id'] ?? null,
                fn ($query, $editionId) => $query->where('edition_id', $editionId)
            )
            ->withCount('teamPlayers')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.edition-teams.index', [
            'editionTeams' => $editionTeams,
            'filters' => $filters,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EditionTeam::class);

        return view('admin.edition-teams.create', [
            // Not edition-filtered: consistent with the same "show the
            // full eligible list, let server-side validation reject
            // duplicates" approach already used by Player Registration's
            // player dropdown, rather than adding an AJAX dependent
            // dropdown for this.
            'editions' => Edition::openForParticipation()->orderByDesc('year')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreEditionTeamRequest $request): RedirectResponse
    {
        $this->authorize('create', EditionTeam::class);

        $this->editionTeams->createEditionTeam($request->validated());

        return redirect()
            ->route('admin.edition-teams.index')
            ->with('success', 'Team added to edition successfully.');
    }

    public function show(EditionTeam $editionTeam): View
    {
        $this->authorize('view', $editionTeam);

        $editionTeam->load(['edition', 'team']);
        $editionTeam->loadCount(['teamPlayers', 'matchesAsTeamA', 'matchesAsTeamB']);

        return view('admin.edition-teams.show', [
            'editionTeam' => $editionTeam,
        ]);
    }

    public function destroy(EditionTeam $editionTeam): RedirectResponse
    {
        $this->authorize('delete', $editionTeam);

        if (! $this->editionTeams->deleteEditionTeam($editionTeam)) {
            return redirect()
                ->route('admin.edition-teams.index')
                ->with('error', 'This team cannot be removed from the edition because tournament data already exists.');
        }

        return redirect()
            ->route('admin.edition-teams.index')
            ->with('success', 'Team removed from edition successfully.');
    }
}
