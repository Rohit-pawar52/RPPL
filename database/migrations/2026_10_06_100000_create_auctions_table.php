<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One player auction per edition. Amounts are whole "points" (a team's
     * purse, a bid, a sold price) — never rupees. Every rule value below is
     * a setting the admin / auctioneer can change; the service reads the
     * current value on each action.
     *
     * current_lot_id deliberately has no foreign key: auctions and
     * auction_lots point at each other, and the service keeps it correct.
     */
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')->unique()->constrained('editions')->cascadeOnDelete();

            // draft | live | paused | completed
            $table->string('status', 12)->default('draft');

            $table->unsignedBigInteger('team_purse')->default(600000);
            $table->unsignedInteger('min_bid')->default(500);
            $table->unsignedInteger('bid_step')->default(500);
            $table->unsignedTinyInteger('min_squad')->default(12);
            $table->unsignedTinyInteger('max_squad')->default(15);

            // Whether the public page shows every bid as it happens, or only
            // the sold result.
            $table->boolean('show_live_bids')->default(true);

            // 1 for the first pass; hold players come back in round 2, 3, ...
            $table->unsignedSmallInteger('round')->default(1);

            $table->unsignedBigInteger('current_lot_id')->nullable()->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
