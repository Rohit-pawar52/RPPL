<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Player\StorePlayerRequest;
use App\Http\Requests\Admin\Player\UpdatePlayerRequest;
use App\Models\Edition;
use App\Models\Player;
use App\Services\Player\PlayerService;
use App\Services\Statistics\PlayerStatisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(
        private readonly PlayerService $players,
        private readonly PlayerStatisticsService $statistics,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Player::class);

        $filters = $request->only(['search', 'primary_role', 'batting_style', 'bowling_style', 'status']);

        $players = Player::query()
            // Deliberately NOT scoped to active() by default: this is the
            // admin management list, which must keep showing both active
            // and inactive players unless the admin explicitly filters.
            // Player::active() is reserved for future selection dropdowns
            // (e.g. registrations), not this screen.
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
            )
            ->when(
                in_array($filters['primary_role'] ?? null, Player::PRIMARY_ROLES, true),
                fn ($query) => $query->where('primary_role', $filters['primary_role'])
            )
            ->when(
                in_array($filters['batting_style'] ?? null, Player::BATTING_STYLES, true),
                fn ($query) => $query->where('batting_style', $filters['batting_style'])
            )
            ->when(
                in_array($filters['bowling_style'] ?? null, Player::BOWLING_STYLES, true),
                fn ($query) => $query->where('bowling_style', $filters['bowling_style'])
            )
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->withCount('playerRegistrations')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.players.index', [
            'players' => $players,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Player::class);

        return view('admin.players.create');
    }

    public function store(StorePlayerRequest $request): RedirectResponse
    {
        $this->authorize('create', Player::class);

        $this->players->createPlayer($request->safe()->except('photo'), $request->file('photo'));

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Player created successfully.');
    }

    public function show(Request $request, Player $player): View
    {
        $this->authorize('view', $player);

        $player->loadCount('playerRegistrations');
        $player->load(['playerRegistrations' => function ($query) {
            $query->with('edition')->latest('registered_at');
        }]);

        // Whitelisted against the player's own registrations rather than
        // any arbitrary Edition id — an edition_id the player was never
        // registered in would just silently yield all-zero stats, but
        // scoping it here keeps the filter meaningful and avoids an
        // unnecessary Edition lookup for an id that could never apply.
        $selectedEdition = $request->filled('edition_id')
            ? $player->playerRegistrations->pluck('edition')->firstWhere('id', $request->integer('edition_id'))
            : null;

        $matchHistory = $this->statistics->getPlayerMatchHistory($player, $selectedEdition)
            ->appends($request->only('edition_id'));

        return view('admin.players.show', [
            'player' => $player,
            'selectedEdition' => $selectedEdition,
            'stats' => $this->statistics->getPlayerStatistics($player, $selectedEdition),
            'matchHistory' => $matchHistory,
        ]);
    }

    public function edit(Player $player): View
    {
        $this->authorize('update', $player);

        return view('admin.players.edit', [
            'player' => $player,
        ]);
    }

    public function update(UpdatePlayerRequest $request, Player $player): RedirectResponse
    {
        $this->authorize('update', $player);

        $this->players->updatePlayer($player, $request->safe()->except('photo'), $request->file('photo'));

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Player updated successfully.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $this->authorize('delete', $player);

        if (! $this->players->deletePlayer($player)) {
            return redirect()
                ->route('admin.players.index')
                ->with('error', 'This player cannot be deleted because tournament history exists. Deactivate the player instead if they should no longer be available.');
        }

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Player deleted successfully.');
    }
}
