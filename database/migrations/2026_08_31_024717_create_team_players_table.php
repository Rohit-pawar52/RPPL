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
        Schema::create('team_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_team_id')
                ->constrained('edition_teams')
                ->cascadeOnDelete();

            $table->foreignId('player_registration_id')
                ->constrained('player_registrations')
                ->cascadeOnDelete();

            $table->unsignedInteger('jersey_number')->nullable();

            $table->enum('role', [
                'batter',
                'bowler',
                'all_rounder',
                'wicket_keeper',
            ])->nullable();

            $table->timestamps();

            $table->unique('player_registration_id');

            $table->unique([
                'edition_team_id',
                'jersey_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_players');
    }
};
