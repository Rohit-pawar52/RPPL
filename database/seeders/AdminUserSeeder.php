<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The default admin login (admin@gmail.com / 12345678), so there is always a way into the admin panel
 * - including on a database that was seeded earlier, e.g. the Render deploy (docker/start.sh runs this
 * on every start).
 *
 * Creates the user only when it is missing: a password changed later is never reset, and the
 * account is never overwritten. Safe to run any number of times. This password is publicly
 * known - change it after the first login (set SEED_ADMIN=false on Render to stop creating the account).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent; makes this seeder work on a freshly migrated database that has no roles yet.
        $this->call(RoleSeeder::class);

        User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'role_id' => Role::where('slug', 'admin')->firstOrFail()->id,
                'name' => 'Admin',
                'is_active' => true,
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]
        );
    }
}
