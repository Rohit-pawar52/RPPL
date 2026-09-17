<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Edition\StoreEditionRequest;
use App\Http\Requests\Admin\Edition\UpdateEditionRequest;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Models\PlayerRegistration;
use App\Services\Edition\EditionService;
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

        $this->editions->createEdition($request->validated());

        return redirect()
            ->route('admin.editions.index')
            ->with('success', 'Edition created successfully.');
    }

    public function show(Edition $edition): View
    {
        $this->authorize('view', $edition);

        $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']);

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

        return view('admin.editions.show', [
            'edition' => $edition,
            'standings' => $this->standings->getEditionStandings($edition),
            'leaderboard' => $this->statistics->getEditionLeaderboard($edition),
            'records' => $this->statistics->getEditionRecords($edition),
            'financeSummary' => [
                'income' => $financeIncome,
                'expense' => $financeExpense,
                'balance' => $financeIncome - $financeExpense,
            ],
            'contributionSummary' => [
                'contributors' => (int) $contributionTotals->contributors,
                'total' => (float) $contributionTotals->total,
            ],
        ]);
    }

    /**
     * Admin-only PDF snapshot of the tournament for this Edition — a
     * presentation layer over the same StandingsService/
     * PlayerStatisticsService/registration-and-match data show() already
     * loads, never a duplicate calculation. Deliberately excludes the
     * EditionTransaction finance ledger and committee contributions,
     * which are a separate reporting domain (Phases 3.25/3.27).
     */
    public function reportPdf(Edition $edition): Response
    {
        $this->authorize('view', $edition);

        $edition->loadCount(['playerRegistrations', 'editionTeams', 'matches']);

        $registrationCounts = PlayerRegistration::query()
            ->where('edition_id', $edition->id)
            ->select('payment_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('payment_status')
            ->pluck('count', 'payment_status');

        $paidRegistrationFees = (float) PlayerRegistration::query()
            ->where('edition_id', $edition->id)
            ->where('payment_status', 'paid')
            ->sum('registration_fee');

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

        $this->editions->updateEdition($edition, $request->validated());

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
}
