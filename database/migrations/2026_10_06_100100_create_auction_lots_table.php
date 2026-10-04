<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per player in the auction: where they are (pending, live on
     * the block, sold, on hold, unsold) and the bid standing on them.
     * `version` goes up on every change, so a tap made on a stale screen can
     * be recognised and refused.
     */
    public function up(): void
    {
        Schema::create('auction_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
            $table->foreignId('player_registration_id')->constrained('player_registrations')->cascadeOnDelete();

            // pending | live | sold | hold | unsold
            $table->string('status', 10)->default('pending');
            $table->unsignedSmallInteger('round')->default(1);

            $table->unsignedBigInteger('current_bid')->nullable();
            $table->foreignId('leading_edition_team_id')->nullable()->constrained('edition_teams')->nullOnDelete();

            // Set once sold: the squad row the sale created.
            $table->foreignId('team_player_id')->nullable()->constrained('team_players')->nullOnDelete();

            $table->unsignedInteger('version')->default(0);

            $table->timestamp('called_at')->nullable();
            $table->timestamp('sold_at')->nullable();

            $table->timestamps();

            $table->unique(['auction_id', 'player_registration_id']);
            $table->index(['auction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_lots');
    }
};
