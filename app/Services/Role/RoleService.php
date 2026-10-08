<?php

namespace App\Services\Role;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * Creating, renaming and deleting roles, and the permissions each holds.
 *
 * Every write that touches more than one row runs in a transaction, and the
 * permissions always go through Role::syncPermissions(), which keeps only
 * delegable catalog keys (so `roles.manage`, `users.manage` or a made-up key
 * can never be stored, whatever a caller passes) and adds `.view` for every
 * `.manage`. A role's slug is generated once, from its name, and never
 * changes afterwards: code refers to the built-in roles by slug.
 */
class RoleService
{
    /**
     * @param  iterable<mixed>  $permissions
     */
    public function createRole(string $name, iterable $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions) {
            $role = Role::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);

            $role->syncPermissions($permissions);

            return $role;
        });
    }

    /**
     * Renames the role and replaces its permissions. The slug is left alone,
     * and the admin role - which always holds everything - is refused.
     *
     * @param  iterable<mixed>  $permissions
     */
    public function updateRole(Role $role, string $name, iterable $permissions): Role
    {
        if ($role->isAdmin()) {
            throw new LogicException('The administrator role cannot be edited.');
        }

        DB::transaction(function () use ($role, $name, $permissions) {
            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);
        });

        return $role;
    }

    /**
     * Deletes a role that nobody uses. Returns false instead of letting the
     * users.role_id foreign key fail, so the controller can say how many
     * users still hold it. The built-in roles are refused outright.
     */
    public function deleteRole(Role $role): bool
    {
        if ($role->isSystem()) {
            throw new LogicException('A built-in role cannot be deleted.');
        }

        return DB::transaction(function () use ($role) {
            if ($role->users()->exists()) {
                return false;
            }

            DB::table('role_permissions')->where('role_id', $role->getKey())->delete();

            return (bool) $role->delete();
        });
    }

    /**
     * The slug for a new role: the name as a slug, with -2, -3 ... added when
     * it is taken. A built-in slug is never handed out, even if that role was
     * renamed or is missing, because code treats those slugs as special (the
     * `admin` slug holds every permission).
     */
    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'role';
        }

        $taken = array_merge(Permissions::SYSTEM_ROLES, Role::query()->pluck('slug')->all());

        $slug = $base;

        for ($n = 2; in_array($slug, $taken, true); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /**
     * Whether another role already has this name, or one that reads the same
     * (differing only in case, spacing or punctuation: "News editor!" and
     * "news-editor" are the same role to a person choosing from a list).
     */
    public function nameIsTaken(string $name, ?Role $ignore = null): bool
    {
        $lower = mb_strtolower(trim($name));
        $slug = Str::slug($name);

        return Role::query()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->get(['id', 'name'])
            ->contains(fn (Role $other) => mb_strtolower($other->name) === $lower
                || ($slug !== '' && Str::slug($other->name) === $slug));
    }

    /**
     * How many permissions each role holds, by role id (roles with none are
     * absent). Counts only keys that are still delegable catalog entries.
     *
     * @return array<int, int>
     */
    public function permissionCounts(): array
    {
        return DB::table('role_permissions')
            ->whereIn('permission', Permissions::keys(delegableOnly: true))
            ->selectRaw('role_id, count(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
