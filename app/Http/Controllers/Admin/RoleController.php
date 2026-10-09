<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Services\Role\RoleService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin CRUD for roles and the permissions each one holds (the list of
 * permissions lives in App\Support\Permissions). Access to this whole module
 * is admin only, because `roles.view` / `roles.manage` can never be given to
 * a role (RolePolicy).
 *
 * Rules worth knowing: a role's slug is made from its name once and never
 * changes; the admin role always holds every permission, so it can be looked
 * at but not edited or deleted; the other built-in roles (scorer,
 * auctioneer) can be renamed and have their permissions changed but not be
 * deleted; a role that still has users is never deleted - the admin gets a
 * friendly message instead of the foreign key's raw database error.
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        // A handful of rows, so no pagination: built-in roles first, then the rest by name.
        $roles = Role::query()
            ->withCount('users')
            ->get()
            ->sortBy(fn (Role $role) => [$role->isSystem() ? 0 : 1, Str::lower($role->name)])
            ->values();

        return view('admin.roles.index', [
            'roles' => $roles,
            'permissionCounts' => $this->roles->permissionCounts(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'groups' => Permissions::grouped(delegableOnly: true),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $role = $this->roles->createRole(
            $request->validated('name'),
            $request->validated('permissions') ?? [],
        );

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('Role ":name" created. Give a login this role under Users.', ['name' => $role->name]));
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        $role->loadCount('users');

        return view('admin.roles.show', [
            'role' => $role,
            'groups' => Permissions::grouped(delegableOnly: true),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        $role->loadCount('users');

        return view('admin.roles.edit', [
            'role' => $role,
            'groups' => Permissions::grouped(delegableOnly: true),
            // What the role effectively holds (a stored `.manage` counts as its `.view` too).
            'held' => array_values(array_filter(
                Permissions::keys(delegableOnly: true),
                fn (string $key) => $role->hasPermission($key),
            )),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $this->roles->updateRole(
            $role,
            $request->validated('name'),
            $request->validated('permissions') ?? [],
        );

        $redirect = redirect()
            ->route('admin.roles.index')
            ->with('success', __('Role ":name" updated.', ['name' => $role->name]));

        // Make the consequence visible: without panel.access nobody with this role can sign in.
        $users = $role->users()->count();

        if ($users > 0 && ! $role->hasPermission('panel.access')) {
            $redirect->with('warning', $users === 1
                ? __('1 user has this role and can no longer sign in to the admin panel.')
                : __(':count users have this role and can no longer sign in to the admin panel.', ['count' => $users]));
        }

        return $redirect;
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if (! $this->roles->deleteRole($role)) {
            $users = $role->users()->count();

            return redirect()
                ->route('admin.roles.index')
                ->with('error', $users === 1
                    ? __('The role ":name" cannot be deleted because 1 user still has it. Give those users another role first.', ['name' => $role->name])
                    : __('The role ":name" cannot be deleted because :count users still have it. Give those users another role first.', ['name' => $role->name, 'count' => $users]));
        }

        return redirect()
            ->route('admin.roles.index')
            ->with('success', __('Role ":name" deleted.', ['name' => $role->name]));
    }
}
