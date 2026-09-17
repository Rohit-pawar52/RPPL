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
        Schema::create('innings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('innings_number');

            $table->foreignId('batting_team_id')
                ->constrained('edition_teams')
                ->restrictOnDelete();

            $table->foreignId('bowling_team_id')
                ->constrained('edition_teams')
                ->restrictOnDelete();

            $table->enum('status', [
                'scheduled',
                'live',
                'completed',
                'abandoned',
            ])->default('scheduled');

            // Cached score values
            $table->unsignedInteger('legal_balls')->default(0);
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedTinyInteger('total_wickets')->default(0);
            $table->unsignedInteger('extras')->default(0);

            $table->timestamps();

            $table->unique([
                'match_id',
                'innings_number',
            ]);

            $table->index('batting_team_id');
            $table->index('bowling_team_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('innings');
    }
};
