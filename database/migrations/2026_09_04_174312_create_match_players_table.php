<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('match_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $table->foreignId('team_player_id')
                ->constrained('team_players')
                ->cascadeOnDelete();

            $table->boolean('is_captain')->default(false);
            $table->boolean('is_wicket_keeper')->default(false);

            $table->timestamps();

            $table->unique([
                'match_id',
                'team_player_id',
            ]);

            $table->index([
                'match_id',
                'is_captain',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_players');
    }
};
