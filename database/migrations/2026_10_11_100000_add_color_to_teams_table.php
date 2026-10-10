<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The colour a team wears on the auction pages (a #rrggbb). Empty means "automatic": the auction hands out one
     * from its palette, exactly as before this column existed.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
