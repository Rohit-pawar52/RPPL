<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamPlayer\StoreTeamPlayerRequest;
use App\Http\Requests\Admin\TeamPlayer\UpdateTeamPlayerRequest;
use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamPlayerController extends Controller
{
    public function __construct(private readonly TeamPlayerService $teamPlayers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', TeamPlayer::class);

        $filters = $request->only(['search', 'edition_team_id']);

        $teamPlayers = TeamPlayer::query()
            ->with(['editionTeam.edition', 'editionTeam.team', 'playerRegistration.player'])
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->whereHas('playerRegistration.player', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%');
                })
            )
            ->when(
                $filters['edition_team_id'] ?? null,
                fn ($query, $editionTeamId) => $query->where('edition_team_id', $editionTeamId)
            )
            ->withCount('matchPlayers')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.team-players.index', [
            'teamPlayers' => $teamPlayers,
            'filters' => $filters,
            'editionTeams' => EditionTeam::with(['edition', 'team'])->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', TeamPlayer::class);

        // Not further cross-filtered by edition on the registration side
        // (and vice versa): consistent with the same "show the full
        // eligible list, let server-side validation reject mismatches"
        // approach already used by Player Registration and Edition Team,
        // rather than adding an AJAX dependent dropdown for this.
        $editionTeams = EditionTeam::query()
            ->whereHas('edition', fn ($query) => $query->where('status', '!=', 'completed'))
            ->whereHas('team', fn ($query) => $query->where('is_active', true))
            ->with(['edition', 'team'])
            ->get();

        $registrations = PlayerRegistration::query()
            ->whereHas('player', fn ($query) => $query->where('is_active', true))
            ->whereDoesntHave('teamPlayer')
            ->with(['player', 'edition'])
            ->get();

        return view('admin.team-players.create', [
            'editionTeams' => $editionTeams,
            'registrations' => $registrations,
            'roles' => TeamPlayer::ROLES,
        ]);
    }

    public function store(StoreTeamPlayerRequest $request): RedirectResponse
    {
        $this->authorize('create', TeamPlayer::class);

        $this->teamPlayers->createTeamPlayer($request->validated());

        return redirect()
            ->route('admin.team-players.index')
            ->with('success', 'Player added to squad successfully.');
    }

    public function show(TeamPlayer $teamPlayer): View
    {
        $this->authorize('view', $teamPlayer);

        $teamPlayer->load(['editionTeam.edition', 'editionTeam.team', 'playerRegistration.player']);
        $teamPlayer->loadCount('matchPlayers');

        return view('admin.team-players.show', [
            'teamPlayer' => $teamPlayer,
        ]);
    }

    public function edit(TeamPlayer $teamPlayer): View
    {
        $this->authorize('update', $teamPlayer);

        $teamPlayer->load(['editionTeam.edition', 'editionTeam.team', 'playerRegistration.player']);

        return view('admin.team-players.edit', [
            'teamPlayer' => $teamPlayer,
            'roles' => TeamPlayer::ROLES,
        ]);
    }

    public function update(UpdateTeamPlayerRequest $request, TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorize('update', $teamPlayer);

        $this->teamPlayers->updateTeamPlayer($teamPlayer, $request->validated());

        return redirect()
            ->route('admin.team-players.index')
            ->with('success', 'Squad player updated successfully.');
    }

    public function destroy(TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorize('delete', $teamPlayer);

        if (! $this->teamPlayers->deleteTeamPlayer($teamPlayer)) {
            return redirect()
                ->route('admin.team-players.index')
                ->with('error', 'This squad player cannot be removed because match history exists.');
        }

        return redirect()
            ->route('admin.team-players.index')
            ->with('success', 'Player removed from squad successfully.');
    }
}
