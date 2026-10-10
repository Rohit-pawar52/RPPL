<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what in an auction: every call, bid, sale, undo, correction and change of rules, with the
     * player, team and amount in plain columns (the text is made from them when it is shown, so it follows
     * the viewer's language). A dispute at the hall is settled from here.
     */
    public function up(): void
    {
        Schema::create('auction_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('auction_lot_id')->nullable()->constrained('auction_lots')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type', 40);
            $table->string('player_name')->nullable();
            $table->string('team_name')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auction_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_events');
    }
};
