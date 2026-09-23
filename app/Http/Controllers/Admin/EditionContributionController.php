<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersAdminTables;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditionContribution\StoreEditionContributionRequest;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Services\Finance\CommitteeDuesService;
use App\Services\Finance\EditionContributionService;
use App\View\Composers\BrandingComposer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Contribution ledger — every contribution belongs to exactly one
 * Contributor (Phase 3.48; there is no separate "committee" identity
 * any more). No edit/update: a contribution's financial history is
 * never silently rewritten — a mistaken entry is deleted (which
 * atomically removes its linked finance transaction via
 * EditionContributionService) and re-recorded correctly.
 */
class EditionContributionController extends Controller
{
    use FiltersAdminTables;

    private const ALLOWED_SORTS = ['contributed_at', 'amount'];

    public function __construct(
        private readonly EditionContributionService $contributions,
        private readonly CommitteeDuesService $dues,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EditionContribution::class);

        $dateRange = $this->validateDateRange($request);
        $filters = $request->only(['edition_id', 'contributor_id', 'search']) + $dateRange;
        [$sort, $direction] = $this->allowedSort($request, self::ALLOWED_SORTS, 'contributed_at');
        $perPage = $this->allowedPerPage($request);

        $query = $this->contributionQuery($filters);

        $totalContributions = (clone $query)->sum('amount');

        $contributions = $query
            ->with(['edition', 'contributor.committeeMemberships'])
            ->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.edition-contributions.index', [
            'contributions' => $contributions,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
            'contributors' => Contributor::orderBy('name')->get(['id', 'name']),
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

        $filters = $request->only(['edition_id', 'contributor_id', 'search']) + $this->validateDateRange($request);

        return $this->streamContributionsCsv(
            $this->contributionQuery($filters)->with(['contributor.committeeMemberships', 'createdBy']),
            $this->exportFilename($filters)
        );
    }

    /**
     * Exports exactly the rows explicitly checked on the current index
     * page — never "every record matching the current filters" (that is
     * what export() above already does). Ignores $filters entirely:
     * selected_ids take precedence over the ambient filter set, but each
     * id must still be a real contribution (`exists:` rule below) so an
     * authorized admin can only ever export rows that genuinely exist —
     * viewAny is the same gate the index/export routes already use, so
     * this doesn't open any access the admin didn't already have. Same
     * pattern as PlayerRegistrationController::exportSelected().
     */
    public function exportSelected(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', EditionContribution::class);

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:edition_contributions,id'],
        ]);

        return $this->streamContributionsCsv(
            EditionContribution::query()
                ->whereIn('id', $validated['selected_ids'])
                ->with(['contributor.committeeMemberships', 'createdBy']),
            'rppl-contributions-selected.csv'
        );
    }

    private function streamContributionsCsv(Builder $query, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Reference', 'Contributor Name', 'Source', 'Amount',
                'Contribution Date', 'Notes', 'Recorded By', 'Transaction ID',
            ]);

            $query->chunkById(200, function ($contributions) use ($handle) {
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
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', EditionContribution::class);

        return view('admin.edition-contributions.create', [
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
            'contributors' => Contributor::active()->orderBy('name')->get(['id', 'name']),
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

    /**
     * Backs the create-contribution form's live "Committee Member"
     * badge/target/paid/remaining preview (Phase 3.48) — a small,
     * purpose-built JSON endpoint in the same spirit as this project's
     * other lightweight AJAX reads (e.g. the public live-match-data
     * endpoint), not a general-purpose API. Read-only, same viewAny
     * gate as the rest of this controller.
     */
    public function duesPreview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EditionContribution::class);

        $validated = $request->validate([
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'contributor_id' => ['required', 'integer', 'exists:contributors,id'],
        ]);

        $edition = Edition::findOrFail($validated['edition_id']);
        $contributor = Contributor::findOrFail($validated['contributor_id']);

        $isCommitteeMember = $contributor->isCommitteeMemberOf($edition);

        if (! $isCommitteeMember) {
            return response()->json(['is_committee_member' => false]);
        }

        $dues = $this->dues->duesFor($contributor, $edition);

        return response()->json([
            'is_committee_member' => true,
            // Pre-formatted with money() server-side (currency symbol,
            // decimals) so the JS side only ever inserts trusted, already
            // -escaped-by-construction display strings — no client-side
            // currency formatting/duplication of that rule.
            'dues' => [
                'target' => money($dues['target']),
                'paid' => money($dues['paid']),
                'remaining' => money($dues['remaining']),
                'status' => $dues['status'],
            ],
        ]);
    }

    public function show(EditionContribution $editionContribution): View
    {
        $this->authorize('view', $editionContribution);

        $editionContribution->load(['edition', 'contributor.committeeMemberships', 'transaction', 'createdBy']);

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

        $editionContribution->load(['edition', 'contributor']);

        return view('admin.edition-contributions.receipt', [
            'contribution' => $editionContribution,
        ]);
    }

    public function receiptPdf(EditionContribution $editionContribution): Response
    {
        $this->authorize('view', $editionContribution);

        $editionContribution->load(['edition', 'contributor']);

        $pdf = Pdf::loadView('admin.edition-contributions.receipt', [
            'contribution' => $editionContribution,
        ])->setPaper('a4');

        return $pdf->download('rppl-contribution-receipt-'.$editionContribution->receiptReference().'.pdf');
    }

    /**
     * One combined PDF (one A4 page per contribution, via CSS
     * page-break-after in receipts-batch.blade.php) for exactly the rows
     * explicitly checked on the current index page — same selected_ids
     * contract and viewAny gate as exportSelected() above, just rendered
     * as receipts instead of CSV rows.
     *
     * receipts-batch.blade.php (unlike receipt.blade.php) is not in
     * AppServiceProvider::configureBranding()'s explicit view list, so
     * $branding is composed onto it manually here rather than widening
     * that shared list for one new view.
     */
    public function receiptsSelectedPdf(Request $request): Response
    {
        $this->authorize('viewAny', EditionContribution::class);

        $validated = $request->validate([
            'selected_ids' => ['required', 'array', 'min:1'],
            'selected_ids.*' => ['integer', 'exists:edition_contributions,id'],
        ]);

        $contributions = EditionContribution::query()
            ->whereIn('id', $validated['selected_ids'])
            ->with(['edition', 'contributor'])
            ->orderBy('contributed_at')
            ->get();

        $view = view('admin.edition-contributions.receipts-batch', [
            'contributions' => $contributions,
        ]);

        app(BrandingComposer::class)->compose($view);

        $pdf = Pdf::loadHTML($view->render())->setPaper('a4');

        return $pdf->download('rppl-contribution-receipts-selected.pdf');
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
            ->when($filters['contributor_id'] ?? null, fn ($query, $id) => $query->where('contributor_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->whereHas('contributor', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            )
            ->tap(fn ($query) => $this->dateRangeFilter($query, 'contributed_at', $filters['from_date'] ?? null, $filters['to_date'] ?? null));
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
