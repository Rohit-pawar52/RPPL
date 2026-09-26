<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 — a single, reusable, append-only audit/event log for every
 * scoring-adjacent action that is deliberately NOT a Delivery: penalty
 * runs, retired hurt/out, a manual strike correction, a mid-over bowler
 * change, manual innings completion, an innings/match reopen, and a
 * Super Over result. One small table with a `type` discriminator and a
 * generic nullable `payload` JSON column, rather than a dedicated table
 * per event type — these are all "who did what, when, and why" facts of
 * the same shape, never queried against each other, so one table keeps
 * this additive without inventing a family of near-identical schemas.
 *
 * `reason` is NOT NULL: every event type this phase writes has a
 * mandatory reason by its own frozen rule (penalty runs, retirement,
 * change strike, bowler change, manual completion, reopen, super over)
 * — there is no event type recorded here that is reason-optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $table->foreignId('innings_id')
                ->nullable()
                ->constrained('innings')
                ->nullOnDelete();

            $table->string('type', 40);

            $table->foreignId('match_player_id')
                ->nullable()
                ->constrained('match_players')
                ->nullOnDelete();

            $table->foreignId('awarded_team_id')
                ->nullable()
                ->constrained('edition_teams')
                ->nullOnDelete();

            $table->unsignedInteger('runs')->nullable();
            $table->json('payload')->nullable();
            $table->text('reason');

            $table->foreignId('performed_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['match_id', 'type']);
            $table->index(['innings_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_events');
    }
};
