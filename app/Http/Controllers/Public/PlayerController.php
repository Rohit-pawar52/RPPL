<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\Statistics\PlayerStatisticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public player directory/profile. Read-only, no authorization.
 * Statistics are read entirely through PlayerStatisticsService — this
 * controller never computes batting/bowling figures itself.
 */
class PlayerController extends Controller
{
    public function __construct(private readonly PlayerStatisticsService $statistics) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));

        $players = Player::query()
            ->active()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->with('latestRegistration.teamPlayer.editionTeam.team')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('public.players.index', [
            'players' => $players,
            'search' => $search,
        ]);
    }

    /**
     * Deliberately does NOT reject an inactive player — a historical
     * profile link must remain usable even after deactivation.
     */
    public function show(Request $request, Player $player): View
    {
        $player->load(['playerRegistrations' => function ($query) {
            $query->with(['edition', 'teamPlayer.editionTeam.team'])->latest('registered_at');
        }]);

        // Whitelisted against the player's own registrations, exactly as
        // the admin player page does — an edition_id the player was
        // never registered in is simply ignored rather than looked up.
        $selectedEdition = $request->filled('edition_id')
            ? $player->playerRegistrations->pluck('edition')->firstWhere('id', $request->integer('edition_id'))
            : null;

        $currentRegistration = $selectedEdition
            ? $player->playerRegistrations->firstWhere('edition_id', $selectedEdition->id)
            : $player->playerRegistrations->first();

        $matchHistory = $this->statistics->getPlayerMatchHistory($player, $selectedEdition)
            ->appends($request->only('edition_id'));

        return view('public.players.show', [
            'player' => $player,
            'selectedEdition' => $selectedEdition,
            'currentTeam' => $currentRegistration?->teamPlayer?->editionTeam?->team,
            'stats' => $this->statistics->getPlayerStatistics($player, $selectedEdition),
            'matchHistory' => $matchHistory,
        ]);
    }
}
