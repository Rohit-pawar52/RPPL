<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditionTeam\AddSeasonTeamsRequest;
use App\Http\Requests\Admin\EditionTeam\CreateSeasonTeamRequest;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\Team;
use App\Services\EditionTeam\EditionTeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The teams of ONE season, managed from inside that season (the Edition
 * hub's "Teams" card): add several existing teams at once, create a new
 * team on the spot, remove a team. The standalone Edition Teams pages keep
 * working; both use the same EditionTeamService rules.
 */
class SeasonTeamController extends Controller
{
    public function __construct(private readonly EditionTeamService $editionTeams) {}

    public function index(Edition $edition): View
    {
        $this->authorize('viewAny', EditionTeam::class);

        $editionTeams = $edition->editionTeams()
            ->with('team')
            ->withCount(['teamPlayers', 'matchesAsTeamA', 'matchesAsTeamB'])
            ->get()
            ->sortBy(fn (EditionTeam $editionTeam) => mb_strtolower($editionTeam->team->name))
            ->values();

        $inEdition = $editionTeams->pluck('team_id')->all();

        return view('admin.editions.teams.index', [
            'edition' => $edition,
            'editionTeams' => $editionTeams,
            // Only teams that can still be added: active, and not in this season.
            'availableTeams' => Team::active()->whereNotIn('id', $inEdition)->orderBy('name')->get(),
            'canAdd' => $edition->status !== 'completed',
        ]);
    }

    public function store(AddSeasonTeamsRequest $request, Edition $edition): RedirectResponse
    {
        $this->authorize('create', EditionTeam::class);

        if ($blocked = $this->completedBlock($edition)) {
            return $blocked;
        }

        $added = $this->editionTeams->addTeams($edition, $request->validated('team_ids'));

        return redirect()
            ->route('admin.editions.teams.index', $edition)
            ->with('success', $added === 1 ? '1 team added to the season.' : "{$added} teams added to the season.");
    }

    public function storeNew(CreateSeasonTeamRequest $request, Edition $edition): RedirectResponse
    {
        $this->authorize('create', EditionTeam::class);

        if ($blocked = $this->completedBlock($edition)) {
            return $blocked;
        }

        $this->editionTeams->createTeamAndAdd($edition, $request->safe()->except('logo'), $request->file('logo'));

        return redirect()
            ->route('admin.editions.teams.index', $edition)
            ->with('success', __('Team created and added to the season.'));
    }

    public function destroy(Edition $edition, EditionTeam $editionTeam): RedirectResponse
    {
        $this->authorize('delete', $editionTeam);

        // The route binds both ids; a team of another season is simply not found.
        abort_unless($editionTeam->edition_id === $edition->id, 404);

        $name = $editionTeam->team->name;

        if (! $this->editionTeams->deleteEditionTeam($editionTeam)) {
            return redirect()
                ->route('admin.editions.teams.index', $edition)
                ->with('error', "{$name} already has matches in this season, so it cannot be removed.");
        }

        return redirect()
            ->route('admin.editions.teams.index', $edition)
            ->with('success', "{$name} removed from the season (its squad entries went with it; the players' registrations are unchanged).");
    }

    private function completedBlock(Edition $edition): ?RedirectResponse
    {
        if ($edition->status !== 'completed') {
            return null;
        }

        return redirect()
            ->route('admin.editions.teams.index', $edition)
            ->with('error', __('This season is completed, so new teams cannot be added.'));
    }
}
