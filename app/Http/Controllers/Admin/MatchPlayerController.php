<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MatchPlayer\StoreMatchPlayerRequest;
use App\Http\Requests\Admin\MatchPlayer\UpdateMatchPlayerRequest;
use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use App\Services\MatchPlayer\MatchPlayerService;
use Illuminate\Http\RedirectResponse;
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

        $eligiblePlayers = function (int $editionTeamId) use ($selected) {
            $selectedTeamPlayerIds = ($selected->get($editionTeamId) ?? collect())
                ->pluck('team_player_id');

            return TeamPlayer::query()
                ->where('edition_team_id', $editionTeamId)
                ->whereNotIn('id', $selectedTeamPlayerIds)
                ->whereHas('playerRegistration.player', fn ($query) => $query->where('is_active', true))
                ->with('playerRegistration.player')
                ->get();
        };

        return view('admin.match-players.index', [
            'match' => $match,
            'canModify' => $this->matchPlayers->canModifyPlayingXI($match),
            'teamASelected' => $selected->get($match->edition_team_a_id) ?? collect(),
            'teamBSelected' => $selected->get($match->edition_team_b_id) ?? collect(),
            'teamAEligible' => $eligiblePlayers($match->edition_team_a_id),
            'teamBEligible' => $eligiblePlayers($match->edition_team_b_id),
        ]);
    }

    public function store(StoreMatchPlayerRequest $request, GameMatch $match): RedirectResponse
    {
        $this->authorize('create', MatchPlayer::class);

        if (! $this->matchPlayers->canModifyPlayingXI($match)) {
            return redirect()
                ->route('admin.matches.players.index', $match)
                ->with('error', 'The Playing XI for this match can no longer be modified.');
        }

        $teamPlayer = TeamPlayer::findOrFail($request->validated('team_player_id'));

        $this->matchPlayers->addPlayer($match, $teamPlayer);

        return redirect()
            ->route('admin.matches.players.index', $match)
            ->with('success', 'Player added to the Playing XI.');
    }

    public function update(UpdateMatchPlayerRequest $request, GameMatch $match, MatchPlayer $matchPlayer): RedirectResponse
    {
        abort_unless($matchPlayer->match_id === $match->id, 404);

        $this->authorize('update', $matchPlayer);

        if (! $this->matchPlayers->canModifyPlayingXI($match)) {
            return redirect()
                ->route('admin.matches.players.index', $match)
                ->with('error', 'The Playing XI for this match can no longer be modified.');
        }

        if ($request->validated('designation') === 'captain') {
            $this->matchPlayers->setCaptain($matchPlayer);
        } else {
            $this->matchPlayers->setWicketKeeper($matchPlayer);
        }

        return redirect()
            ->route('admin.matches.players.index', $match)
            ->with('success', 'Playing XI updated.');
    }

    public function destroy(GameMatch $match, MatchPlayer $matchPlayer): RedirectResponse
    {
        abort_unless($matchPlayer->match_id === $match->id, 404);

        $this->authorize('delete', $matchPlayer);

        if (! $this->matchPlayers->canModifyPlayingXI($match)) {
            return redirect()
                ->route('admin.matches.players.index', $match)
                ->with('error', 'The Playing XI for this match can no longer be modified.');
        }

        if (! $this->matchPlayers->removePlayer($matchPlayer)) {
            return redirect()
                ->route('admin.matches.players.index', $match)
                ->with('error', 'This player cannot be removed because match scoring history exists.');
        }

        return redirect()
            ->route('admin.matches.players.index', $match)
            ->with('success', 'Player removed from the Playing XI.');
    }
}
