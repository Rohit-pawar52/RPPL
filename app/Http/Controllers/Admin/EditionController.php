<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Edition\StoreEditionRequest;
use App\Http\Requests\Admin\Edition\UpdateEditionRequest;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\GameMatch;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use App\Services\Edition\EditionService;
use App\Services\Settings\DisplayTimezoneFormatter;
use App\Services\Statistics\PlayerStatisticsService;
use App\Services\Statistics\StandingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EditionController extends Controller
{
    public function __construct(
        private readonly EditionService $editions,
        private readonly PlayerStatisticsService $statistics,
        private readonly StandingsService $standings,
        private readonly DisplayTimezoneFormatter $displayTimezone,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Edition::class);

        $filters = $request->only(['search', 'status', 'year']);

        $editions = Edition::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%')
            )
            ->when(
                in_array($filters['status'] ?? null, Edition::STATUSES, true),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                $filters['year'] ?? null,
                fn ($query, $year) => $query->where('year', $year)
            )
            ->withCount(['playerRegistrations', 'editionTeams', 'matches'])
            ->orderByDesc('year')
            ->paginate(15)
            ->withQueryString();

        return view('admin.editions.index', [
            'editions' => $editions,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Edition::class);

        return view('admin.editions.create', [
            'statuses' => Edition::STATUSES,
        ]);
    }

    public function store(StoreEditionRequest $request): RedirectResponse
    {
        $this->authorize('create', Edition::class);

        $this->editions->createEdition($this->withRegistrationPeriod($request->validated(), $request));

        return redirect()
            ->route('admin.editions.index')
            ->with('success', 'Edition created successfully.');
    }

    public function show(Request $request, Edition $edition): View
    {
        $this->authorize('view', $edition);

        $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']);

        // Money (the ledger and the contributions) is only worked out for a role that may see finance:
        // editions.view is enough to open this page, and must not be a way to read the books. The hub
        // draws its Finance card from these, and only when they are there.
        $financeSummary = null;
        $contributionSummary = null;

        if ($request->user()->hasPermission('finance.view')) {
            $financeTotals = EditionTransaction::query()
                ->where('edition_id', $edition->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income")
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense")
                ->first();
            $financeIncome = (float) $financeTotals->income;
            $financeExpense = (float) $financeTotals->expense;

            $contributionTotals = EditionContribution::query()
                ->where('edition_id', $edition->id)
                ->selectRaw('COUNT(DISTINCT committee_member_id) as contributors')
                ->selectRaw('COALESCE(SUM(amount), 0) as total')
                ->first();

            $financeSummary = [
                'income' => $financeIncome,
                'expense' => $financeExpense,
                'balance' => $financeIncome - $financeExpense,
            ];
            $contributionSummary = [
                'contributors' => (int) $contributionTotals->contributors,
                'total' => (float) $contributionTotals->total,
            ];
        }

        $registrations = PlayerRegistration::where('edition_id', $edition->id);
        $squadPlayers = TeamPlayer::whereHas('editionTeam', fn ($query) => $query->where('edition_id', $edition->id))->count();
        $matchStatuses = GameMatch::where('edition_id', $edition->id)->select('match_status')->selectRaw('COUNT(*) as total')->groupBy('match_status')->pluck('total', 'match_status');

        return view('admin.editions.show', [
            'edition' => $edition,
            'cards' => [
                'registrations' => [
                    'total' => $edition->player_registrations_count,
                    'pending' => (clone $registrations)->where('payment_status', 'pending')->count(),
                ],
                'squads' => [
                    'players' => $squadPlayers,
                    'without_team' => (clone $registrations)->whereDoesntHave('teamPlayer')->count(),
                ],
                'matches' => [
                    'played' => (int) ($matchStatuses['completed'] ?? 0),
                    // Still to be played: not finished, not called off.
                    'remaining' => (int) collect(['scheduled', 'toss', 'live'])->sum(fn ($status) => $matchStatuses[$status] ?? 0),
                ],
            ],
            'standings' => $this->standings->getEditionStandings($edition),
            'leaderboard' => $this->statistics->getEditionLeaderboard($edition),
            'records' => $this->statistics->getEditionRecords($edition),
            'financeSummary' => $financeSummary,
            'contributionSummary' => $contributionSummary,
        ]);
    }

    /**
     * PDF snapshot of the tournament for this Edition, for whoever may view
     * it (editions.view) — a presentation layer over the same
     * StandingsService/PlayerStatisticsService/registration-and-match data
     * show() already loads, never a duplicate calculation. Deliberately
     * excludes the EditionTransaction finance ledger and committee
     * contributions, which are a separate reporting domain (Phases
     * 3.25/3.27). The one money line it has — the paid registration fees —
     * is only worked out and printed for a role that also holds
     * finance.view.
     */
    public function reportPdf(Request $request, Edition $edition): Response
    {
        $this->authorize('view', $edition);

        $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']);

        $registrationCounts = PlayerRegistration::query()
            ->where('edition_id', $edition->id)
            ->select('payment_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status');

        // null = not to be shown (the report-pdf view prints the line only when it has an amount).
        $paidRegistrationFees = $request->user()->hasPermission('finance.view')
            ? (float) PlayerRegistration::query()
                ->where('edition_id', $edition->id)
                ->where('payment_status', 'paid')
                ->sum('registration_fee')
            : null;

        $matchStatusCounts = $edition->matches()
            ->select('match_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('match_status')
            ->pluck('count', 'match_status');

        $teams = $edition->editionTeams()
            ->with('team')
            ->withCount('teamPlayers')
            ->get();

        $matches = $edition->matches()
            ->with(['teamA.team', 'teamB.team'])
            ->orderBy('scheduled_at')
            ->get();

        $pdf = Pdf::loadView('admin.editions.report-pdf', [
            'edition' => $edition,
            'registrationCounts' => $registrationCounts,
            'paidRegistrationFees' => $paidRegistrationFees,
            'matchStatusCounts' => $matchStatusCounts,
            'teams' => $teams,
            'matches' => $matches,
            'standings' => $this->standings->getEditionStandings($edition),
            'leaderboard' => $this->statistics->getEditionLeaderboard($edition),
        ])->setPaper('a4');

        return $pdf->download('rppl-'.Str::slug($edition->name).'-'.$edition->year.'-summary.pdf');
    }

    public function edit(Edition $edition): View
    {
        $this->authorize('update', $edition);

        return view('admin.editions.edit', [
            'edition' => $edition,
            'statuses' => Edition::STATUSES,
        ]);
    }

    public function update(UpdateEditionRequest $request, Edition $edition): RedirectResponse
    {
        $this->authorize('update', $edition);

        $this->editions->updateEdition($edition, $this->withRegistrationPeriod($request->validated(), $request, $edition));

        return redirect()
            ->route('admin.editions.index')
            ->with('success', 'Edition updated successfully.');
    }

    public function destroy(Edition $edition): RedirectResponse
    {
        $this->authorize('delete', $edition);

        if (! $this->editions->deleteEdition($edition)) {
            return redirect()
                ->route('admin.editions.index')
                ->with('error', 'This edition cannot be deleted because tournament data already exists.');
        }

        return redirect()
            ->route('admin.editions.index')
            ->with('success', 'Edition deleted successfully.');
    }

    /**
     * Converts the naive display-timezone datetime-local inputs to UTC
     * (same convention as GameMatchController's scheduled_at). An already
     * dispatched closing reminder is left untouched: V1 never re-arms it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withRegistrationPeriod(array $data, Request $request, ?Edition $edition = null): array
    {
        foreach (['registration_opens_at', 'registration_closes_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->displayTimezone->parseFromDisplayTimezone($data[$field]);
            }
        }

        if ($edition?->registration_reminder_dispatched_at !== null) {
            unset($data['registration_reminder_enabled'], $data['registration_reminder_minutes_before']);
        } else {
            $data['registration_reminder_enabled'] = $request->boolean('registration_reminder_enabled');
        }

        return $data;
    }
}
