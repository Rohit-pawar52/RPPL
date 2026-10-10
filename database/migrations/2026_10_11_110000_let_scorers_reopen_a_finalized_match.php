<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A match now finalizes by itself when its result is certain, so the person scoring needs a way to take a wrong
     * one back at once: the built-in Scorer role gets "Reopen a finalized match" (it can be taken away again under
     * System Management > Roles).
     */
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'scorer')->value('id');

        if ($roleId !== null) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission' => 'matches.reopen']);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'scorer')->value('id');

        if ($roleId !== null) {
            DB::table('role_permissions')->where(['role_id' => $roleId, 'permission' => 'matches.reopen'])->delete();
        }
    }
};
