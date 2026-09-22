<?php

namespace Database\Seeders\Demo;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The two demo login accounts for the stakeholder demo dataset. Local/
 * demo credentials only — never suggested for production use (see the
 * README "Demo data" section for where these are documented).
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->firstOrFail();
        $scorerRole = Role::where('slug', 'scorer')->firstOrFail();

        User::firstOrCreate(
            ['email' => 'admin@rppl.test'],
            [
                'role_id' => $adminRole->id,
                'name' => 'RPPL Admin',
                'is_active' => true,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'scorer@rppl.test'],
            [
                'role_id' => $scorerRole->id,
                'name' => 'RPPL Scorer',
                'is_active' => true,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
    }
}
