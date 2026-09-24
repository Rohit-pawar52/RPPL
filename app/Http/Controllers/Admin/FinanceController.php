<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Services\Finance\CommitteeDuesService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Finance "Overview" tab (Phase 3.48) — the landing page for the
 * consolidated Finance sidebar entry. Read-only: reuses
 * EditionTransaction::summaryForEdition() (the same calculation the
 * dashboard/Reports already use, so the three screens can never
 * disagree) and CommitteeDuesService for the committee dues summary.
 * Never redefines either calculation itself.
 */
class FinanceController extends Controller
{
    public function __construct(private readonly CommitteeDuesService $dues) {}

    public function overview(Request $request): View
    {
        $this->authorize('manage-tournament');

        $edition = $this->resolveEdition($request);
        $editions = Edition::orderByDesc('year')->get(['id', 'name', 'year']);

        $financeSummary = $edition ? EditionTransaction::summaryForEdition($edition->id) : ['income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        $contributionTotal = $edition ? (float) EditionContribution::where('edition_id', $edition->id)->sum('amount') : 0.0;
        $duesSummary = $edition ? $this->dues->summaryForEdition($edition) : null;
        $duesRows = $edition ? $this->dues->duesForEdition($edition) : [];

        return view('admin.finance.overview', [
            'edition' => $edition,
            'editions' => $editions,
            'financeSummary' => $financeSummary,
            'contributionTotal' => $contributionTotal,
            'duesSummary' => $duesSummary,
            'duesRows' => $duesRows,
        ]);
    }

    /**
     * Same deterministic "currently relevant edition" rule used
     * throughout the admin panel (see DashboardController).
     */
    private function resolveEdition(Request $request): ?Edition
    {
        if ($request->filled('edition_id')) {
            return Edition::find($request->integer('edition_id'));
        }

        return Edition::where('status', 'active')->latest('year')->first()
            ?? Edition::where('status', 'upcoming')->orderBy('year')->first()
            ?? Edition::where('status', 'completed')->latest('year')->first();
    }
}
