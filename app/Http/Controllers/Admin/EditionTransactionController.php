<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditionTransaction\StoreEditionTransactionRequest;
use App\Http\Requests\Admin\EditionTransaction\UpdateEditionTransactionRequest;
use App\Models\Edition;
use App\Models\EditionTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manual edition income/expense ledger. Writes here are trivial
 * single-row create/update/delete with no business rule beyond
 * created_by immutability (enforced simply by never accepting it from
 * the request) — deliberately no service layer, per this phase's
 * explicit instruction not to scaffold one for nothing but bare
 * Eloquent calls.
 */
class EditionTransactionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EditionTransaction::class);

        $filters = $request->only(['edition_id', 'type', 'search']);

        $transactions = $this->transactionQuery($filters)
            ->with(['edition', 'createdBy'])
            ->withExists('contribution')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.edition-transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
            'summary' => $this->summaryFor($filters['edition_id'] ?? null),
        ]);
    }

    /**
     * Streamed UTF-8 CSV of the same filtered ledger index() shows —
     * same approach as Phase 3.28's PlayerRegistration export, no
     * shared ExportService: two small controller exports are still
     * simpler than a premature abstraction. Contribution-linked rows
     * are included like any other ledger row, with a human-readable
     * Source label instead of any internal contribution id.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', EditionTransaction::class);

        $filters = $request->only(['edition_id', 'type', 'search']);

        return response()->streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Excel-friendly UTF-8 BOM so accented text renders
            // correctly when the file is opened directly in Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Transaction ID', 'Date', 'Edition', 'Type', 'Category',
                'Description', 'Amount', 'Source', 'Created By',
            ]);

            $this->transactionQuery($filters)
                ->with(['edition', 'createdBy'])
                ->withExists('contribution')
                ->chunkById(200, function ($transactions) use ($handle) {
                    foreach ($transactions as $transaction) {
                        fputcsv($handle, [
                            $transaction->id,
                            $transaction->transaction_date->format('Y-m-d'),
                            $transaction->edition->name,
                            ucfirst($transaction->type),
                            $transaction->category ?? '',
                            $transaction->description ?? '',
                            number_format($transaction->amount, 2, '.', ''),
                            $transaction->contribution_exists ? 'Committee Contribution' : 'Manual',
                            $transaction->createdBy->name,
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
        $this->authorize('create', EditionTransaction::class);

        return view('admin.edition-transactions.create', [
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
        ]);
    }

    public function store(StoreEditionTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', EditionTransaction::class);

        EditionTransaction::create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
        ]));

        return redirect()
            ->route('admin.edition-transactions.index')
            ->with('success', 'Transaction recorded successfully.');
    }

    public function show(EditionTransaction $editionTransaction): View
    {
        $this->authorize('view', $editionTransaction);

        $editionTransaction->load(['edition', 'createdBy', 'contribution']);

        return view('admin.edition-transactions.show', [
            'transaction' => $editionTransaction,
        ]);
    }

    public function edit(EditionTransaction $editionTransaction): View|RedirectResponse
    {
        $this->authorize('update', $editionTransaction);

        if ($blocked = $this->blockIfLinkedToContribution($editionTransaction)) {
            return $blocked;
        }

        return view('admin.edition-transactions.edit', [
            'transaction' => $editionTransaction,
            'editions' => Edition::orderByDesc('year')->get(['id', 'name']),
        ]);
    }

    /**
     * created_by is never part of $request->validated() (it isn't in
     * the FormRequest's rules at all), so update() cannot touch it
     * regardless of what the request body contains.
     */
    public function update(UpdateEditionTransactionRequest $request, EditionTransaction $editionTransaction): RedirectResponse
    {
        $this->authorize('update', $editionTransaction);

        if ($blocked = $this->blockIfLinkedToContribution($editionTransaction)) {
            return $blocked;
        }

        $editionTransaction->update($request->validated());

        return redirect()
            ->route('admin.edition-transactions.index')
            ->with('success', 'Transaction updated successfully.');
    }

    public function destroy(EditionTransaction $editionTransaction): RedirectResponse
    {
        $this->authorize('delete', $editionTransaction);

        if ($blocked = $this->blockIfLinkedToContribution($editionTransaction)) {
            return $blocked;
        }

        $editionTransaction->delete();

        return redirect()
            ->route('admin.edition-transactions.index')
            ->with('success', 'Transaction deleted successfully.');
    }

    /**
     * A transaction created automatically by EditionContributionService
     * (Phase 3.26) must never be edited/deleted through this generic
     * Finance CRUD — only through deleting the contribution itself,
     * which removes both rows atomically. Returns null when the
     * transaction is unlinked and the caller may proceed normally.
     */
    private function blockIfLinkedToContribution(EditionTransaction $editionTransaction): ?RedirectResponse
    {
        if (! $editionTransaction->contribution()->exists()) {
            return null;
        }

        return redirect()
            ->route('admin.edition-transactions.index')
            ->with('error', 'This transaction is managed by a committee contribution and cannot be modified directly.');
    }

    /**
     * The single source of truth for ledger filtering, shared by
     * index() and export() so the two can never quietly diverge.
     * Filtering only — no pagination/eager-loading/response concerns,
     * those stay in each caller.
     *
     * @param  array<string, mixed>  $filters
     */
    private function transactionQuery(array $filters): Builder
    {
        return EditionTransaction::query()
            ->when($filters['edition_id'] ?? null, fn ($query, $editionId) => $query->where('edition_id', $editionId))
            ->when(
                in_array($filters['type'] ?? null, EditionTransaction::TYPES, true),
                fn ($query) => $query->where('type', $filters['type'])
            )
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('category', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
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
            ? "rppl-finance-{$edition->year}.csv"
            : 'rppl-finance-ledger.csv';
    }

    /**
     * Deliberately ignores the type/search filters — the three figures
     * are only meaningful together against the same edition scope.
     * Calculation itself lives on EditionTransaction::summaryForEdition()
     * (Phase 3.42), shared with the admin dashboard, so the two screens
     * can never disagree on a given edition's balance.
     *
     * @return array{income: float, expense: float, balance: float}
     */
    private function summaryFor(?int $editionId): array
    {
        return EditionTransaction::summaryForEdition($editionId);
    }
}
