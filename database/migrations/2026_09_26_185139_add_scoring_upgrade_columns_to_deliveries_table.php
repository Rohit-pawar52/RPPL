<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 — additive columns only, nothing rewritten on existing rows.
 * Historical deliveries keep their old lumped wide_runs value with
 * wide_running_runs defaulting to 0 (all-penalty, no distinguishable
 * running-runs component) — an accepted, documented historical-data
 * ambiguity, never silently reinterpreted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Wide-run split (frozen rule 10): wide_runs becomes the
            // fixed mandatory penalty (1) going forward; running-runs
            // physically completed between the wickets on a wide are
            // now separately representable and separately contribute to
            // strike-rotation parity.
            $table->unsignedTinyInteger('wide_running_runs')->default(0)->after('wide_runs');

            // No Ball + Free Hit (frozen rule 3).
            $table->boolean('is_free_hit')->default(false)->after('is_legal_delivery');
            $table->string('no_ball_reason')->nullable()->after('is_free_hit');

            // Explicit is_wide/is_no_ball flags replace the old single-
            // select extra_type request concept (frozen rule 11): a
            // no-ball and a bye/leg-bye can now coexist on one delivery,
            // which a single mutually-exclusive extra_type could never
            // represent. wide_runs/no_ball_runs/bye_runs/leg_bye_runs
            // stay exactly as they were; only how a delivery is
            // classified as illegal changes.
            $table->boolean('is_wide')->default(false)->after('no_ball_reason');
            $table->boolean('is_no_ball')->default(false)->after('is_wide');

            // Short run (frozen rule 12) + a general "actual runs
            // physically completed" fact, kept independent from the
            // credited runs columns above. Null means "no divergence
            // from credited runs" (the overwhelmingly common case) —
            // strike-rotation parity falls back to the credited total
            // whenever this is null, so ordinary scoring is completely
            // unaffected.
            $table->boolean('is_short_run')->default(false)->after('is_wicket');
            $table->unsignedTinyInteger('runs_physically_run')->nullable()->after('is_short_run');

            // Run-out/obstructing-field end confirmation (frozen rule
            // 19): lets the scorer state which end the surviving batter
            // actually ended up at, overriding the computed slot only
            // when explicitly provided.
            $table->enum('confirmed_survivor_end', ['striker', 'non_striker'])->nullable()->after('fielder_match_player_id');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'wide_running_runs',
                'is_free_hit',
                'no_ball_reason',
                'is_wide',
                'is_no_ball',
                'is_short_run',
                'runs_physically_run',
                'confirmed_survivor_end',
            ]);
        });
    }
};
