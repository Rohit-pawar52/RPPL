<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeamPlayer\AddSquadPlayersRequest;
use App\Http\Requests\Admin\TeamPlayer\CreateOfflineSquadPlayerRequest;
use App\Http\Requests\Admin\TeamPlayer\UpdateSquadRequest;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The squads of ONE season, managed from inside that season (the Edition
 * hub's "Squads" card): an overview of every team's squad, and a page per
 * team to add players in bulk (the auction result, with each sold amount),
 * edit jersey / role / amount in the list, remove a player, or add a
 * player who registered offline. The standalone Squads pages keep working;
 * both use TeamPlayerService.
 */
class SeasonSquadController extends Controller
{
    public function __construct(private readonly TeamPlayerService $teamPlayers) {}

    public function index(Edition $edition): View
    {
        $this->authorize('viewAny', TeamPlayer::class);

        $editionTeams = $edition->editionTeams()
            ->with('team')
            ->withCount('teamPlayers')
            ->withSum('teamPlayers as sold_total', 'sold_amount')
            ->get()
            ->sortBy(fn (EditionTeam $editionTeam) => mb_strtolower($editionTeam->team->name))
            ->values();

        return view('admin.editions.squads.index', [
            'edition' => $edition,
            'editionTeams' => $editionTeams,
            'withoutTeam' => $this->unassigned($edition)->count(),
        ]);
    }

    public function show(Edition $edition, EditionTeam $editionTeam): View
    {
        $this->authorize('viewAny', TeamPlayer::class);
        $this->belongs($edition, $editionTeam);

        $editionTeam->load('team');

        $squad = $editionTeam->teamPlayers()
            ->with('playerRegistration.player')
            ->withCount('matchPlayers')
            ->get()
            ->sortBy(fn (TeamPlayer $teamPlayer) => mb_strtolower($teamPlayer->playerRegistration->player->name))
            ->values();

        return view('admin.editions.squads.show', [
            'edition' => $edition,
            'editionTeam' => $editionTeam,
            'squad' => $squad,
            'soldTotal' => $squad->sum(fn (TeamPlayer $teamPlayer) => (float) $teamPlayer->sold_amount),
            'roles' => TeamPlayer::ROLES,
            // Only this season's players who are not yet in any team.
            'available' => $this->unassigned($edition)->with('player')->get()
                ->sortBy(fn (PlayerRegistration $registration) => mb_strtolower($registration->player->name))
                ->values(),
            'canAdd' => $edition->status !== 'completed',
        ]);
    }

    public function store(AddSquadPlayersRequest $request, Edition $edition, EditionTeam $editionTeam): RedirectResponse
    {
        $this->authorize('create', TeamPlayer::class);
        $this->belongs($edition, $editionTeam);

        if ($blocked = $this->completedBlock($edition, $editionTeam)) {
            return $blocked;
        }

        $added = $this->teamPlayers->addPlayers($editionTeam, $request->amounts());

        return redirect()
            ->route('admin.editions.squads.show', [$edition, $editionTeam])
            ->with('success', $added === 1 ? '1 player added to the squad.' : "{$added} players added to the squad.");
    }

    public function storeOffline(CreateOfflineSquadPlayerRequest $request, Edition $edition, EditionTeam $editionTeam): RedirectResponse
    {
        $this->authorize('create', TeamPlayer::class);
        $this->belongs($edition, $editionTeam);

        if ($blocked = $this->completedBlock($edition, $editionTeam)) {
            return $blocked;
        }

        try {
            $this->teamPlayers->createOfflinePlayer($editionTeam, $request->validated());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors(), 'offlinePlayer');
        }

        return redirect()
            ->route('admin.editions.squads.show', [$edition, $editionTeam])
            ->with('success', $request->validated('name').' registered and added to the squad.');
    }

    public function update(UpdateSquadRequest $request, Edition $edition, EditionTeam $editionTeam): RedirectResponse
    {
        // The policy only looks at the role, so a blank model is enough here.
        $this->authorize('update', new TeamPlayer);
        $this->belongs($edition, $editionTeam);

        $this->teamPlayers->updateSquad($editionTeam, $request->validated('players'));

        return redirect()
            ->route('admin.editions.squads.show', [$edition, $editionTeam])
            ->with('success', 'Squad saved.');
    }

    public function destroy(Edition $edition, EditionTeam $editionTeam, TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorize('delete', $teamPlayer);
        $this->belongs($edition, $editionTeam);
        abort_unless($teamPlayer->edition_team_id === $editionTeam->id, 404);

        $name = $teamPlayer->playerRegistration->player->name;

        if (! $this->teamPlayers->deleteTeamPlayer($teamPlayer)) {
            return redirect()
                ->route('admin.editions.squads.show', [$edition, $editionTeam])
                ->with('error', "{$name} has played in a match, so cannot be removed from the squad.");
        }

        return redirect()
            ->route('admin.editions.squads.show', [$edition, $editionTeam])
            ->with('success', "{$name} removed from the squad (their registration is unchanged).");
    }

    /**
     * The season's registrations that are not in any squad, and whose
     * player is active.
     */
    private function unassigned(Edition $edition)
    {
        return PlayerRegistration::query()
            ->where('edition_id', $edition->id)
            ->whereDoesntHave('teamPlayer')
            ->whereHas('player', fn ($query) => $query->where('is_active', true));
    }

    /**
     * Both ids are in the URL; a team of another season is simply not found.
     */
    private function belongs(Edition $edition, EditionTeam $editionTeam): void
    {
        abort_unless($editionTeam->edition_id === $edition->id, 404);
    }

    private function completedBlock(Edition $edition, EditionTeam $editionTeam): ?RedirectResponse
    {
        if ($edition->status !== 'completed') {
            return null;
        }

        return redirect()
            ->route('admin.editions.squads.show', [$edition, $editionTeam])
            ->with('error', 'This season is completed, so new players cannot be added to a squad.');
    }
}
