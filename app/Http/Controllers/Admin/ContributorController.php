<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersAdminTables;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contributor\StoreContributorRequest;
use App\Http\Requests\Admin\Contributor\UpdateContributorRequest;
use App\Models\Contributor;
use App\Models\Edition;
use App\Services\Contributor\ContributorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * THE master people list for anyone who contributes money to RPPL
 * (Phase 3.48) — a general "Chanda" contributor and a committee member
 * are the same kind of record here; committee membership is managed
 * separately, per edition, from the Finance "Committee" tab (see
 * EditionCommitteeMemberController), never from this form.
 */
class ContributorController extends Controller
{
    use FiltersAdminTables;

    private const ALLOWED_SORTS = ['name', 'contributions_count'];

    public function __construct(private readonly ContributorService $contributors) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Contributor::class);

        $filters = $request->only(['search', 'status']);
        [$sort, $direction] = $this->allowedSort($request, self::ALLOWED_SORTS, 'name', 'asc');
        $perPage = $this->allowedPerPage($request);

        $currentEdition = $this->currentEdition();

        $contributors = Contributor::query()
            // Deliberately NOT scoped to active() by default: this is
            // the admin management list, which must keep showing both
            // active and inactive contributors unless explicitly filtered.
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%')
            )
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->withCount('contributions')
            ->when($currentEdition, fn ($query) => $query->with([
                'committeeMemberships' => fn ($query) => $query->where('edition_id', $currentEdition->id),
            ]))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.contributors.index', [
            'contributors' => $contributors,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
            'currentEdition' => $currentEdition,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Contributor::class);

        return view('admin.contributors.create');
    }

    public function store(StoreContributorRequest $request): RedirectResponse
    {
        $this->authorize('create', Contributor::class);

        $this->contributors->createContributor($request->safe()->except('photo'), $request->file('photo'));

        return redirect()
            ->route('admin.contributors.index')
            ->with('success', 'Contributor added successfully.');
    }

    public function show(Contributor $contributor): View
    {
        $this->authorize('view', $contributor);

        $contributor->loadCount('contributions');

        $currentEdition = $this->currentEdition();

        return view('admin.contributors.show', [
            'contributor' => $contributor,
            'currentEdition' => $currentEdition,
            'isCurrentCommitteeMember' => $currentEdition ? $contributor->isCommitteeMemberOf($currentEdition) : false,
        ]);
    }

    public function edit(Contributor $contributor): View
    {
        $this->authorize('update', $contributor);

        return view('admin.contributors.edit', [
            'contributor' => $contributor,
        ]);
    }

    public function update(UpdateContributorRequest $request, Contributor $contributor): RedirectResponse
    {
        $this->authorize('update', $contributor);

        $this->contributors->updateContributor($contributor, $request->safe()->except('photo'), $request->file('photo'));

        return redirect()
            ->route('admin.contributors.index')
            ->with('success', 'Contributor updated successfully.');
    }

    public function destroy(Contributor $contributor): RedirectResponse
    {
        $this->authorize('delete', $contributor);

        // Once contribution history exists, the record is deactivated,
        // not deleted. ContributorService only removes the owned photo
        // file after the row delete has actually succeeded, so a
        // blocked deletion always leaves it in place. Historical
        // Contributors are never deleted merely for not being on any
        // current committee.
        if (! $this->contributors->deleteContributor($contributor)) {
            return redirect()
                ->route('admin.contributors.index')
                ->with('error', 'This contributor cannot be deleted because contribution history exists. Deactivate the contributor instead.');
        }

        return redirect()
            ->route('admin.contributors.index')
            ->with('success', 'Contributor deleted successfully.');
    }

    /**
     * Same deterministic rule as the admin dashboard/public homepage:
     * active edition, else soonest upcoming, else most recently
     * completed, else none. Duplicated locally rather than extracted
     * into a shared service, matching this project's existing
     * convention (see DashboardController's own copy of this method).
     */
    private function currentEdition(): ?Edition
    {
        return Edition::where('status', 'active')->latest('year')->first()
            ?? Edition::where('status', 'upcoming')->orderBy('year')->first()
            ?? Edition::where('status', 'completed')->latest('year')->first();
    }
}
