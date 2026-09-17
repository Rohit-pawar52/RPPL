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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

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

        $query = EditionContribution::query()
            ->when($filters['edition_id'] ?? null, fn ($query, $id) => $query->where('edition_id', $id))
            ->when($filters['committee_member_id'] ?? null, fn ($query, $id) => $query->where('committee_member_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->whereHas('committeeMember', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('contributor', fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                })
            );

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
}
