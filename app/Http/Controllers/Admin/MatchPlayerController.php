<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MatchPlayer\SyncMatchPlayersRequest;
use App\Http\Requests\Admin\MatchPlayer\UpdateMatchPlayerRequest;
use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use App\Services\MatchPlayer\MatchPlayerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MatchPlayerController extends Controller
{
    public function __construct(private readonly MatchPlayerService $matchPlayers) {}

    public function index(GameMatch $match): View
    {
        $this->authorize('viewAny', MatchPlayer::class);

        $match->load(['edition', 'teamA.team', 'teamB.team']);

        $selected = MatchPlayer::query()
            ->where('match_id', $match->id)
            ->with(['teamPlayer.playerRegistration.player'])
            ->get()
            ->groupBy(fn (MatchPlayer $matchPlayer) => $matchPlayer->teamPlayer->edition_team_id);

        $squadFor = fn (int $editionTeamId) => TeamPlayer::query()
            ->where('edition_team_id', $editionTeamId)
            ->orderBy('jersey_number')
            ->with('playerRegistration.player')
            ->get();

        $teamAsquad = $squadFor($match->edition_team_a_id);
        $teamBsquad = $squadFor($match->edition_team_b_id);

        return view('admin.match-players.index', [
            'match' => $match,
            'canModify' => $this->matchPlayers->canModifyPlayingXI($match),
            'teamASelected' => $selected->get($match->edition_team_a_id) ?? collect(),
            'teamBSelected' => $selected->get($match->edition_team_b_id) ?? collect(),
            'teamASquad' => $teamAsquad,
            'teamBSquad' => $teamBsquad,
            'teamAAutoSelectIds' => $this->deterministicEleven($teamAsquad),
            'teamBAutoSelectIds' => $this->deterministicEleven($teamBsquad),
        ]);
    }

    /**
     * Bulk Playing XI selection (replaces the old one-player-at-a-time
     * Add workflow): one submission, one team, exactly 11 players,
     * persisted atomically. See MatchPlayerService::syncPlayingXi()'s
     * docblock for why an unchanged selection never disturbs an
     * existing captain/wicket-keeper designation.
     */
    public function sync(SyncMatchPlayersRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('create', MatchPlayer::class);

        if (! $this->matchPlayers->canModifyPlayingXI($match)) {
            return redirect()
                ->route('admin.matches.players.index', $match)
                ->with('error', __('The Playing XI for this match can no longer be modified.'));
        }

        $editionTeam = EditionTeam::findOrFail($request->validated('edition_team_id'));

        $this->matchPlayers->syncPlayingXi($match, $editionTeam, $request->validated('team_player_ids'));

        return redirect()
            ->route('admin.matches.players.index', $match)
            ->with('success', "Playing XI saved for {$editionTeam->team->name}.");
    }

    public function update(UpdateMatchPlayerRequest $request, GameMatch $match, MatchPlayer $matchPlayer): RedirectResponse
    {
        abort_unless($matchPlayer->match_id === $match->id, 404);

        $this->authorize('update', $matchPlayer);

        if (! $this->matchPlayers->canModifyPlayingXI($match)) {
            return redirect()
                ->route('admin.matches.players.index', $match)
                ->with('error', __('The Playing XI for this match can no longer be modified.'));
        }

        if ($request->validated('designation') === 'captain') {
            $this->matchPlayers->setCaptain($matchPlayer);
        } else {
            $this->matchPlayers->setWicketKeeper($matchPlayer);
        }

        return redirect()
            ->route('admin.matches.players.index', $match)
            ->with('success', __('Playing XI updated.'));
    }

    /**
     * The deterministic "Auto Select 11" candidate set (frozen for this
     * UX phase): the first 11 ACTIVE squad players in the squad's own
     * stable jersey_number ordering — never random, so the same squad
     * always auto-selects the same 11 on every page load. Used both to
     * pre-check the UI when a team has no saved Playing XI yet, and to
     * back the "Auto Select 11" button's reset target.
     *
     * @param  Collection<int, TeamPlayer>  $squad
     * @return list<int>
     */
    private function deterministicEleven($squad): array
    {
        return $squad
            ->filter(fn (TeamPlayer $teamPlayer) => $teamPlayer->playerRegistration->player->is_active)
            ->take(11)
            ->pluck('id')
            ->values()
            ->all();
    }
}
