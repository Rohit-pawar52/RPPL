<?php

namespace Database\Seeders;

use App\Models\Role;
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
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
