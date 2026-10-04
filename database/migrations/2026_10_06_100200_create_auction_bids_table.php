<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every bid ever placed, in order. Undo never deletes a row — it stamps
     * cancelled_at — so the history stays complete. The idempotency key
     * makes a double tap or a retried request place exactly one bid.
     */
    public function up(): void
    {
        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auction_lot_id')->constrained('auction_lots')->cascadeOnDelete();
            $table->foreignId('edition_team_id')->constrained('edition_teams')->cascadeOnDelete();

            $table->unsignedBigInteger('amount');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('idempotency_key', 64)->nullable()->unique();

            // A bid that went past the "keep enough to reach the minimum
            // squad" limit because the admin / auctioneer overrode it.
            $table->boolean('is_override')->default(false);

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['auction_lot_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_bids');
    }
};
