<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the roles the application currently needs.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Scorer', 'slug' => 'scorer'],
        ];

        foreach ($roles as $role) {
            // Seeders run with model events off, so the built-in grants are applied here. Only a newly
            // created role gets them: re-running never undoes permissions an admin has edited since.
            $created = Role::firstOrCreate(['slug' => $role['slug']], $role);

            if ($created->wasRecentlyCreated) {
                Permissions::applyDefaults($created);
            }
        }
    }
}
