<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Normal sponsor shows either as a banner strip or as a card in the
     * homepage match row, and those two spots need differently shaped
     * pictures, so the admin picks which one. Empty (null) means banner —
     * what every Normal ad was before this column — and is the only value
     * for Main and Mini sponsors, whose spot is fixed.
     */
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->string('format', 10)->nullable()->after('tier');
        });
    }

    public function down(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
