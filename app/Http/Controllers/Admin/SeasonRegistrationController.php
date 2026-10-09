<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersAdminTables;
use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\TeamPlayer\TeamPlayerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The registrations of ONE season, opened from the Edition hub's
 * "Registrations" card: a compact list with search, payment and team
 * filters, and a bulk "Add to team" for the players who are not in a team
 * yet (no sold amount - that is entered later on the team's squad page).
 * Reviewing a registration, editing and exporting stay on the standalone
 * Player Registrations pages, which this page links to.
 */
class SeasonRegistrationController extends Controller
{
    use FiltersAdminTables;

    /** Value of the team filter for "registrations that are in no team". */
    private const WITHOUT_TEAM = 'none';

    public function __construct(private readonly TeamPlayerService $teamPlayers) {}

    public function index(Request $request, Edition $edition): View
    {
        $this->authorize('viewAny', PlayerRegistration::class);

        $filters = array_filter(
            $request->only(['search', 'payment_status', 'team']),
            fn ($value) => is_string($value) && $value !== ''
        );
        $perPage = $this->allowedPerPage($request);

        $editionTeams = $edition->editionTeams()
            ->with('team')
            ->get()
            ->sortBy(fn (EditionTeam $editionTeam) => mb_strtolower($editionTeam->team->name))
            ->values();

        $registrations = $this->filteredQuery($edition, $filters)
            ->with(['player', 'teamPlayer.editionTeam.team'])
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.editions.registrations.index', [
            'edition' => $edition,
            'registrations' => $registrations,
            'filters' => $filters,
            'perPage' => $perPage,
            'editionTeams' => $editionTeams,
            'withoutTeamValue' => self::WITHOUT_TEAM,
            'canAdd' => $edition->status !== 'completed',
        ]);
    }

    /**
     * Puts the ticked registrations into the chosen team. Those that cannot
     * go in (already in a team, inactive player, another season) are skipped
     * by the service and counted here so the admin sees what happened.
     */
    public function addToTeam(Request $request, Edition $edition): RedirectResponse
    {
        $this->authorize('viewAny', PlayerRegistration::class);
        $this->authorize('create', TeamPlayer::class);

        // Back to the same filtered page the admin was looking at.
        $back = redirect()->route(
            'admin.editions.registrations.index',
            array_filter($request->only(['search', 'payment_status', 'team', 'per_page', 'page']), fn ($value) => is_string($value) && $value !== '')
            + ['edition' => $edition->id]
        );

        if ($edition->status === 'completed') {
            return $back->with('error', __('This season is completed, so players cannot be added to a team.'));
        }

        $ids = collect((array) $request->input('selected'))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $back->with('error', __('Tick at least one player to add.'));
        }

        $teamId = $request->input('edition_team_id');
        $editionTeam = is_numeric($teamId) ? EditionTeam::with('team')->find((int) $teamId) : null;

        if (! $editionTeam) {
            return $back->with('error', __('Choose the team to add the players to.'));
        }

        // A team of another season is simply not found.
        abort_unless($editionTeam->edition_id === $edition->id, 404);

        $added = $this->teamPlayers->addPlayers($editionTeam, $ids->mapWithKeys(fn (int $id) => [$id => null])->all());
        $skipped = $ids->count() - $added;

        if ($added === 0) {
            return $back->with('error', __('No players were added: they are already in a team, inactive, or not registered for this season.'));
        }

        $message = ($added === 1 ? '1 player' : "{$added} players").' added to '.$editionTeam->team->name.'.';

        if ($skipped > 0) {
            $message .= ' '.($skipped === 1 ? '1 was' : "{$skipped} were").' skipped (already in a team, inactive, or not registered for this season).';
        }

        return $back->with('success', $message);
    }

    /**
     * This season's registrations narrowed by the list filters. The search
     * covers the same fields as the main Player Registrations list.
     *
     * @param  array<string, string>  $filters
     */
    private function filteredQuery(Edition $edition, array $filters): Builder
    {
        $team = $filters['team'] ?? null;

        return PlayerRegistration::query()
            ->where('edition_id', $edition->id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('registration_number', 'like', '%'.$search.'%')
                    ->orWhere('village', 'like', '%'.$search.'%')
                    ->orWhere('tehsil', 'like', '%'.$search.'%')
                    ->orWhere('district', 'like', '%'.$search.'%')
                    ->orWhere('submitted_utr', 'like', '%'.$search.'%')
                    ->orWhereHas('player', fn ($query) => $query
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%'));
            }))
            ->when(
                in_array($filters['payment_status'] ?? null, PlayerRegistration::PAYMENT_STATUSES, true),
                fn ($query) => $query->where('payment_status', $filters['payment_status'])
            )
            ->when($team === self::WITHOUT_TEAM, fn ($query) => $query->whereDoesntHave('teamPlayer'))
            ->when(
                ctype_digit((string) $team),
                fn ($query) => $query->whereHas('teamPlayer', fn ($query) => $query->where('edition_team_id', (int) $team))
            );
    }
}
