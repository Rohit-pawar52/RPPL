<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditionContribution\StoreEditionContributionRequest;
use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Services\Finance\EditionContributionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Committee contribution ledger. No edit/update: a contribution's
 * financial history is never silently rewritten — a mistaken entry is
 * deleted (which atomically removes its linked finance transaction via
 * EditionContributionService) and re-recorded correctly.
 */
class EditionContributionController extends Controller
{
    public function __construct(private readonly EditionContributionService $contributions) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EditionContribution::class);

        $filters = $request->only(['edition_id', 'committee_member_id', 'search']);

        $query = $this->contributionQuery($filters);

        $totalContributions = (clone $query)->sum('amount');

        $contributions = $query
            ->with(['edition', 'committeeMember', 'contributor'])
            ->orderByDesc('contributed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.edition-contributions.index', [
            'contributions' => $contributions,
            'filters' => $filters,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
            'members' => CommitteeMember::orderBy('name')->get(['id', 'name']),
            'totalContributions' => $totalContributions,
        ]);
    }

    /**
     * Streamed UTF-8 CSV of individual contribution records — admin-
     * only financial export (Phase 3.43), distinct from the public
     * aggregated contributor leaderboard. Same conventions as
     * PlayerRegistrationController::export()/EditionTransactionController::export():
     * BOM for Excel, fputcsv escaping, chunkById to avoid loading every
     * row into memory. Deliberately excludes phone (not meaningfully
     * needed for a financial report) and any document/image data —
     * this project's contributors have no image data anyway, only
     * player registrations do.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', EditionContribution::class);

        $filters = $request->only(['edition_id', 'committee_member_id', 'search']);

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Reference', 'Contributor Name', 'Source', 'Amount',
                'Contribution Date', 'Notes', 'Recorded By', 'Transaction ID',
            ]);

            $this->contributionQuery($filters)
                ->with(['committeeMember', 'contributor', 'createdBy'])
                ->chunkById(200, function ($contributions) use ($handle) {
                    foreach ($contributions as $contribution) {
                        fputcsv($handle, [
                            $contribution->receiptReference(),
                            $contribution->contributorName(),
                            $contribution->sourceLabel(),
                            number_format($contribution->amount, 2, '.', ''),
                            $contribution->contributed_at->format('Y-m-d'),
                            $contribution->notes ?? '',
                            $contribution->createdBy->name,
                            $contribution->edition_transaction_id,
                        ]);
                    }
                });

            fclose($handle);
        }, $this->exportFilename($filters), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EditionContribution::class);

        return view('admin.edition-contributions.create', [
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
            'members' => CommitteeMember::active()->orderBy('name')->get(['id', 'name']),
            'generalContributors' => Contributor::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreEditionContributionRequest $request): RedirectResponse
    {
        $this->authorize('create', EditionContribution::class);

        $this->contributions->createContribution($request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.edition-contributions.index')
            ->with('success', 'Contribution recorded successfully.');
    }

    public function show(EditionContribution $editionContribution): View
    {
        $this->authorize('view', $editionContribution);

        $editionContribution->load(['edition', 'committeeMember', 'contributor', 'transaction', 'createdBy']);

        return view('admin.edition-contributions.show', [
            'contribution' => $editionContribution,
        ]);
    }

    public function destroy(EditionContribution $editionContribution): RedirectResponse
    {
        $this->authorize('delete', $editionContribution);

        $this->contributions->deleteContribution($editionContribution);

        return redirect()
            ->route('admin.edition-contributions.index')
            ->with('success', 'Contribution deleted successfully.');
    }

    /**
     * HTML preview of the receipt — read-only, same data/template the
     * PDF download renders, so the two can never disagree.
     */
    public function receipt(EditionContribution $editionContribution): View
    {
        $this->authorize('view', $editionContribution);

        $editionContribution->load(['edition', 'committeeMember', 'contributor']);

        return view('admin.edition-contributions.receipt', [
            'contribution' => $editionContribution,
        ]);
    }

    public function receiptPdf(EditionContribution $editionContribution): Response
    {
        $this->authorize('view', $editionContribution);

        $editionContribution->load(['edition', 'committeeMember', 'contributor']);

        $pdf = Pdf::loadView('admin.edition-contributions.receipt', [
            'contribution' => $editionContribution,
        ])->setPaper('a4');

        return $pdf->download('rppl-contribution-receipt-'.$editionContribution->receiptReference().'.pdf');
    }

    /**
     * The single source of truth for contribution filtering, shared by
     * index() and export() so the two can never quietly diverge — same
     * pattern as PlayerRegistrationController::registrationQuery() and
     * EditionTransactionController::transactionQuery().
     *
     * @param  array<string, mixed>  $filters
     */
    private function contributionQuery(array $filters): Builder
    {
        return EditionContribution::query()
            ->when($filters['edition_id'] ?? null, fn ($query, $id) => $query->where('edition_id', $id))
            ->when($filters['committee_member_id'] ?? null, fn ($query, $id) => $query->where('committee_member_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->whereHas('committeeMember', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('contributor', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                })
            );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportFilename(array $filters): string
    {
        $editionId = $filters['edition_id'] ?? null;
        $edition = $editionId ? Edition::find($editionId) : null;

        return $edition
            ? "rppl-contributions-{$edition->year}.csv"
            : 'rppl-contributions.csv';
    }
}
