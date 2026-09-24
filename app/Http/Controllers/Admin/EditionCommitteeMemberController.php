<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Services\Finance\CommitteeDuesService;
use App\Services\Finance\EditionCommitteeMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Finance "Committee" tab (Phase 3.48) — manages which Contributors
 * are on a selected edition's committee, and shows their dues (see
 * CommitteeDuesService). Committee membership carries no financial data
 * of its own; recording an actual payment still goes through the normal
 * contribution form (EditionContributionController), never through this
 * controller.
 */
class EditionCommitteeMemberController extends Controller
{
    public function __construct(
        private readonly EditionCommitteeMembershipService $memberships,
        private readonly CommitteeDuesService $dues,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EditionCommitteeMember::class);

        $edition = $this->resolveEdition($request);
        $editions = Edition::orderByDesc('year')->get(['id', 'name', 'year']);

        $dues = $edition ? $this->dues->duesForEdition($edition) : [];
        $currentMemberIds = collect($dues)->pluck('contributor.id');

        return view('admin.finance.committee', [
            'edition' => $edition,
            'editions' => $editions,
            'dues' => $dues,
            'summary' => $edition ? $this->dues->summaryForEdition($edition) : null,
            'target' => $this->dues->target(),
            'previousEdition' => $edition ? $this->memberships->previousEdition($edition) : null,
            'addableContributors' => $edition
                ? Contributor::active()->whereNotIn('id', $currentMemberIds)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', EditionCommitteeMember::class);

        $validated = $request->validate([
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'contributor_id' => ['required', 'integer', 'exists:contributors,id'],
        ]);

        $edition = Edition::findOrFail($validated['edition_id']);

        $this->memberships->addMember($edition, $validated['contributor_id']);

        return redirect()
            ->route('admin.finance.committee', ['edition_id' => $edition->id])
            ->with('success', 'Committee member added.');
    }

    public function destroy(EditionCommitteeMember $editionCommitteeMember): RedirectResponse
    {
        $this->authorize('delete', $editionCommitteeMember);

        $editionId = $editionCommitteeMember->edition_id;

        if (! $this->memberships->removeMember($editionCommitteeMember->edition, $editionCommitteeMember->contributor_id)) {
            return redirect()
                ->route('admin.finance.committee', ['edition_id' => $editionId])
                ->with('error', 'This contributor has recorded contribution history for this edition and cannot be removed from its committee. Historical membership must stay consistent with financial history.');
        }

        return redirect()
            ->route('admin.finance.committee', ['edition_id' => $editionId])
            ->with('success', 'Committee member removed.');
    }

    public function copyPrevious(Request $request): RedirectResponse
    {
        $this->authorize('create', EditionCommitteeMember::class);

        $validated = $request->validate([
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
        ]);

        $edition = Edition::findOrFail($validated['edition_id']);

        $result = $this->memberships->copyFromPreviousEdition($edition);

        if (! $result['previous_edition']) {
            return redirect()
                ->route('admin.finance.committee', ['edition_id' => $edition->id])
                ->with('error', 'There is no earlier edition to copy a committee from.');
        }

        return redirect()
            ->route('admin.finance.committee', ['edition_id' => $edition->id])
            ->with('success', "{$result['added']} committee member(s) added, {$result['already_existed']} already existed.");
    }

    /**
     * Same deterministic "currently relevant edition" rule used
     * throughout the admin panel (see DashboardController) — used only
     * as the default when no ?edition_id= is given.
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
