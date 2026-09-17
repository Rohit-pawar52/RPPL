<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Application login account administration (admin/scorer accounts).
 * Deliberately no destroy() / delete route: a User may already be
 * referenced by EditionTransaction.created_by / EditionContribution.
 * created_by, and Phase 3.3.5 already established deactivation
 * (is_active) as this project's account-lifecycle mechanism — reused
 * here rather than duplicated. Writes are simple enough (a handful of
 * named fields, one cross-field business rule already owned by
 * UpdateUserRequest) that no service is justified.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'role_id', 'status']);

        $users = User::query()
            ->with('role')
            ->when(
                $filters['search'] ?? null,
                fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })
            )
            ->when($filters['role_id'] ?? null, fn ($query, $roleId) => $query->where('role_id', $roleId))
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role_id' => $request->validated('role_id'),
            'password' => $request->validated('password'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load('role');

        return view('admin.users.show', [
            'targetUser' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'targetUser' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    /**
     * password is included in the update payload only when the admin
     * actually filled it in — a blank field keeps the existing hash
     * untouched, never overwriting it with an empty/garbage value.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role_id' => $request->validated('role_id'),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }
}
