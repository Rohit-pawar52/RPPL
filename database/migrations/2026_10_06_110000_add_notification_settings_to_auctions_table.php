<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Push notifications for an auction (the same Firebase pipeline as match
     * results): one switch for "the auction has started / is over", and an
     * optional minimum — a player sold for at least this many points is
     * announced too (empty = no push per sale, so subscribers are not
     * flooded with a message for every player).
     */
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->boolean('notify_start')->default(true)->after('show_live_bids');
            $table->unsignedBigInteger('notify_sale_min')->nullable()->after('notify_start');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['notify_start', 'notify_sale_min']);
        });
    }
};
