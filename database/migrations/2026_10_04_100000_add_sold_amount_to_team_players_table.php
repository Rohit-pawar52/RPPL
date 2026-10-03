<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a player was bought for in the auction. Optional: a squad entry
     * made without an auction (or before the amount is known) has none.
     */
    public function up(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->decimal('sold_amount', 10, 2)->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('team_players', function (Blueprint $table) {
            $table->dropColumn('sold_amount');
        });
    }
};
