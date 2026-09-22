<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\FiltersAdminTables;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CommitteeMember\StoreCommitteeMemberRequest;
use App\Http\Requests\Admin\CommitteeMember\UpdateCommitteeMemberRequest;
use App\Models\CommitteeMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Committee member directory — a tournament-domain member list, not an
 * application-login system. Writes are trivial single-row
 * create/update calls with no business rule beyond delete-history
 * protection, so (per this phase's instructions) no service exists
 * purely to wrap them.
 */
class CommitteeMemberController extends Controller
{
    use FiltersAdminTables;

    private const ALLOWED_SORTS = ['name', 'contributions_count'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CommitteeMember::class);

        $filters = $request->only(['search', 'status']);
        [$sort, $direction] = $this->allowedSort($request, self::ALLOWED_SORTS, 'name', 'asc');

        $members = CommitteeMember::query()
            // Deliberately NOT scoped to active() by default: this is
            // the admin management list, which must keep showing both
            // active and inactive members unless explicitly filtered.
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
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('admin.committee-members.index', [
            'members' => $members,
            'filters' => $filters,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CommitteeMember::class);

        return view('admin.committee-members.create');
    }

    public function store(StoreCommitteeMemberRequest $request): RedirectResponse
    {
        $this->authorize('create', CommitteeMember::class);

        CommitteeMember::create($request->validated());

        return redirect()
            ->route('admin.committee-members.index')
            ->with('success', 'Committee member added successfully.');
    }

    public function show(CommitteeMember $committeeMember): View
    {
        $this->authorize('view', $committeeMember);

        $committeeMember->loadCount('contributions');
        $committeeMember->load(['contributions' => function ($query) {
            $query->with('edition')->latest('contributed_at')->limit(10);
        }]);

        $totalContributed = $committeeMember->contributions()->sum('amount');

        return view('admin.committee-members.show', [
            'member' => $committeeMember,
            'totalContributed' => $totalContributed,
        ]);
    }

    public function edit(CommitteeMember $committeeMember): View
    {
        $this->authorize('update', $committeeMember);

        return view('admin.committee-members.edit', [
            'member' => $committeeMember,
        ]);
    }

    public function update(UpdateCommitteeMemberRequest $request, CommitteeMember $committeeMember): RedirectResponse
    {
        $this->authorize('update', $committeeMember);

        $committeeMember->update($request->validated());

        return redirect()
            ->route('admin.committee-members.index')
            ->with('success', 'Committee member updated successfully.');
    }

    public function destroy(CommitteeMember $committeeMember): RedirectResponse
    {
        $this->authorize('delete', $committeeMember);

        if ($committeeMember->contributions()->exists()) {
            return redirect()
                ->route('admin.committee-members.index')
                ->with('error', 'This committee member cannot be deleted because contribution history exists. Deactivate the member instead.');
        }

        $committeeMember->delete();

        return redirect()
            ->route('admin.committee-members.index')
            ->with('success', 'Committee member deleted successfully.');
    }
}
