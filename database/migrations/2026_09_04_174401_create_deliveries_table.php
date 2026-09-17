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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('innings_id')
                ->constrained('innings')
                ->cascadeOnDelete();

            // Ordering / ball position
            $table->unsignedInteger('delivery_sequence');
            $table->unsignedTinyInteger('over_number');
            $table->unsignedTinyInteger('ball_number')->default(1);

            // Players participating in this match
            $table->foreignId('striker_match_player_id')
                ->constrained('match_players')
                ->restrictOnDelete();

            $table->foreignId('non_striker_match_player_id')
                ->constrained('match_players')
                ->restrictOnDelete();

            $table->foreignId('bowler_match_player_id')
                ->constrained('match_players')
                ->restrictOnDelete();

            // Runs
            $table->unsignedTinyInteger('runs_off_bat')->default(0);
            $table->unsignedTinyInteger('wide_runs')->default(0);
            $table->unsignedTinyInteger('no_ball_runs')->default(0);
            $table->unsignedTinyInteger('bye_runs')->default(0);
            $table->unsignedTinyInteger('leg_bye_runs')->default(0);
            $table->unsignedTinyInteger('penalty_runs')->default(0);
            $table->unsignedTinyInteger('total_runs')->default(0);

            // Delivery state
            $table->boolean('is_legal_delivery')->default(true);
            $table->boolean('is_wicket')->default(false);

            // Wicket information
            $table->enum('wicket_type', [
                'bowled',
                'caught',
                'lbw',
                'stumped',
                'hit_wicket',
                'run_out',
                'obstructing_field',
            ])->nullable();

            $table->foreignId('dismissed_match_player_id')
                ->nullable()
                ->constrained('match_players')
                ->nullOnDelete();

            $table->foreignId('fielder_match_player_id')
                ->nullable()
                ->constrained('match_players')
                ->nullOnDelete();

            // Commentary / corrections
            $table->text('commentary')->nullable();

            $table->boolean('is_edited')->default(false);
            $table->string('edit_reason')->nullable();

            $table->timestamps();

            // Constraints / query indexes
            $table->unique([
                'innings_id',
                'delivery_sequence',
            ]);

            $table->index([
                'innings_id',
                'is_legal_delivery',
            ]);

            $table->index([
                'striker_match_player_id',
                'innings_id',
            ]);

            $table->index([
                'bowler_match_player_id',
                'innings_id',
            ]);

            $table->index([
                'is_wicket',
                'wicket_type',
            ]);

            $table->index([
                'innings_id',
                'over_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
