<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersAdminTables;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Contributor\StoreContributorRequest;
use App\Http\Requests\Admin\Contributor\UpdateContributorRequest;
use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Services\Contributor\ContributorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * General RPPL contributor ("Chanda") directory — master identity data
 * only (Phase 3.38B1), extended with an optional public profile photo
 * (Phase 3.40). Mirrors CommitteeMemberController's shape closely, with
 * ContributorService added purely to own the photo store/replace/
 * delete lifecycle (see PlayerService for the identical pattern) — no
 * other business rule beyond delete-history protection exists here.
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
            ->with('committeeMember')
            // Relation is named `contributions` (same as CommitteeMember),
            // so withCount() already produces `contributions_count` — no
            // aliasing needed to match CommitteeMemberController's column.
            ->withCount('contributions')
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.contributors.index', [
            'contributors' => $contributors,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Contributor::class);

        return view('admin.contributors.create', [
            'committeeMembers' => $this->linkableCommitteeMembers(),
        ]);
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

        $contributor->load('committeeMember');
        $contributor->loadCount('contributions');

        return view('admin.contributors.show', [
            'contributor' => $contributor,
        ]);
    }

    public function edit(Contributor $contributor): View
    {
        $this->authorize('update', $contributor);

        return view('admin.contributors.edit', [
            'contributor' => $contributor,
            'committeeMembers' => $this->linkableCommitteeMembers($contributor),
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

        // Same historical-protection philosophy as CommitteeMember: once
        // contribution history exists, the record is deactivated, not
        // deleted. Deleting a Contributor never touches its linked
        // CommitteeMember either way; the FK direction already prevents
        // that regardless of this guard. ContributorService only removes
        // the owned photo file after the row delete has actually
        // succeeded, so a blocked deletion always leaves it in place.
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
     * Committee members selectable as an identity link: already-linked
     * members are excluded (the DB unique constraint would reject them
     * anyway), except the contributor's own current link when editing,
     * so the form doesn't need special-casing to keep its current value
     * selectable.
     */
    private function linkableCommitteeMembers(?Contributor $contributor = null)
    {
        return CommitteeMember::query()
            ->whereDoesntHave('contributor', function ($query) use ($contributor) {
                if ($contributor) {
                    $query->where('id', '!=', $contributor->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
