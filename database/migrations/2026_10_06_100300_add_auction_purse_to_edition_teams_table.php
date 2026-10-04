<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A team's own purse for the season's auction. Empty means the
     * auction's default purse (auctions.team_purse).
     */
    public function up(): void
    {
        Schema::table('edition_teams', function (Blueprint $table) {
            $table->unsignedBigInteger('auction_purse')->nullable()->after('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('edition_teams', function (Blueprint $table) {
            $table->dropColumn('auction_purse');
        });
    }
};
