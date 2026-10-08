<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Role extends Model
{
    /** Holds every permission, always; cannot be edited down or deleted. */
    public const ADMIN_SLUG = 'admin';

    protected $fillable = [
        'name',
        'slug',
    ];

    /** @var list<string>|null Permission keys stored for this role, loaded once per instance. */
    private ?array $storedPermissions = null;

    protected static function booted(): void
    {
        // A scorer/auctioneer role created from now on starts with the same rights those roles always
        // had. (Seeders run with model events off, so RoleSeeder applies them explicitly as well.)
        static::created(fn (Role $role) => Permissions::applyDefaults($role));
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isAdmin(): bool
    {
        return $this->slug === self::ADMIN_SLUG;
    }

    /**
     * Built-in roles (admin, scorer, auctioneer): code and policies refer to them by slug, so their
     * slug is fixed and they cannot be deleted.
     */
    public function isSystem(): bool
    {
        return in_array($this->slug, Permissions::SYSTEM_ROLES, true);
    }

    /**
     * The stored permission keys (never includes the admin role's implicit "everything").
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->storedPermissions ??= DB::table('role_permissions')
            ->where('role_id', $this->getKey())
            ->pluck('permission')
            ->all();
    }

    /**
     * Replace this role's permissions with the given keys. Anything that is not a delegable catalog
     * key is dropped, and `.manage` pulls in its `.view`. Not allowed for the admin role.
     *
     * @param  iterable<mixed>  $keys
     */
    public function syncPermissions(iterable $keys): void
    {
        if ($this->isAdmin()) {
            return;
        }

        $keys = Permissions::normalize($keys);

        DB::transaction(function () use ($keys) {
            DB::table('role_permissions')->where('role_id', $this->getKey())->delete();

            if ($keys !== []) {
                DB::table('role_permissions')->insert(array_map(
                    fn (string $key) => ['role_id' => $this->getKey(), 'permission' => $key],
                    $keys
                ));
            }
        });

        $this->storedPermissions = null;
    }

    public function hasPermission(string $key): bool
    {
        // Deny by default: a key that is not in the catalog is granted to nobody (so a typo fails closed).
        if (! Permissions::exists($key)) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        // Access control is never delegated, whatever the database says.
        if (! Permissions::isDelegable($key)) {
            return false;
        }

        $held = $this->permissionKeys();

        if (in_array($key, $held, true)) {
            return true;
        }

        // Being allowed to manage a module includes looking at it.
        return str_ends_with($key, '.view')
            && in_array(substr($key, 0, -5).'.manage', $held, true);
    }
}
