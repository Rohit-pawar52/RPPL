<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the built-in roles could do before roles became editable, copied here as a snapshot so this
     * migration keeps meaning the same thing even after the live catalog (App\Support\Permissions)
     * changes. The `admin` role needs no rows: it always holds every permission.
     */
    private const BUILT_IN = [
        'scorer' => [
            'panel.access', 'dashboard.tournament', 'matches.view', 'matches.run',
            'scoring.score', 'matches.finalize',
        ],
        'auctioneer' => ['panel.access', 'auction.run'],
    ];

    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            // A key from App\Support\Permissions; unknown keys are ignored when checking.
            $table->string('permission', 64);

            $table->unique(['role_id', 'permission']);
        });

        // Existing databases already have scorer/auctioneer accounts: give those roles exactly what
        // they could do before, so nobody gains or loses access by deploying this.
        foreach (self::BUILT_IN as $slug => $permissions) {
            $roleId = DB::table('roles')->where('slug', $slug)->value('id');

            if ($roleId === null) {
                continue;
            }

            DB::table('role_permissions')->insertOrIgnore(array_map(
                fn (string $permission) => ['role_id' => $roleId, 'permission' => $permission],
                $permissions
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
