<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The venue new matches start with (a season is played at one ground).
     * At most one venue is the default; VenueService keeps it that way.
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
