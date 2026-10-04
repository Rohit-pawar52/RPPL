<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Auctioneer role runs the player auction (like Scorer runs
     * matches). Roles are normally created by RoleSeeder, which production
     * does not re-run, so the new role is added here as well. Idempotent.
     */
    public function up(): void
    {
        if (! DB::table('roles')->where('slug', 'auctioneer')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Auctioneer',
                'slug' => 'auctioneer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Only when nobody holds the role; a user's role is never deleted.
        $roleId = DB::table('roles')->where('slug', 'auctioneer')->value('id');

        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
