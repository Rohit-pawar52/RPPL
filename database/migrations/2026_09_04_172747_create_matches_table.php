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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')
                ->constrained('editions')
                ->restrictOnDelete();

            $table->unsignedInteger('match_number')->nullable();

            $table->foreignId('edition_team_a_id')
                ->constrained('edition_teams')
                ->restrictOnDelete();

            $table->foreignId('edition_team_b_id')
                ->constrained('edition_teams')
                ->restrictOnDelete();

            $table->foreignId('venue_id')
                ->nullable()
                ->constrained('venues')
                ->nullOnDelete();

            $table->enum('match_stage', [
                'league',
                'quarter_final',
                'semi_final',
                'final',
            ])->nullable();

            $table->unsignedInteger('overs_per_innings')
                ->default(20);

            $table->dateTime('scheduled_at');

            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->enum('match_status', [
                'scheduled',
                'toss',
                'live',
                'completed',
                'abandoned',
                'cancelled',
            ])->default('scheduled');

            $table->foreignId('toss_winner_team_id')
                ->nullable()
                ->constrained('edition_teams')
                ->nullOnDelete();

            $table->enum('toss_decision', [
                'bat',
                'bowl',
            ])->nullable();

            $table->string('match_result')->nullable();

            $table->foreignId('winner_team_id')
                ->nullable()
                ->constrained('edition_teams')
                ->nullOnDelete();

            $table->enum('result_type', [
                'won',
                'tied',
                'no_result',
                'abandoned',
            ])->nullable();

            $table->enum('win_margin_type', [
                'runs',
                'wickets',
            ])->nullable();

            $table->unsignedInteger('win_margin')->nullable();

            $table->timestamps();

            $table->unique(['edition_id', 'match_number']);

            $table->index(['edition_id', 'match_status']);
            $table->index(['edition_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
