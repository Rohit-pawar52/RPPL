<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 — additive columns only.
 *
 * completion_type/completion_reason (frozen rule 15) replace the old
 * closed-loop inference DeliveryService::undoLastDelivery() used to tell
 * an automatically-completed innings apart from a manually-completed
 * one — now a genuine stored fact instead of a re-derived guess.
 *
 * pending_state (frozen rules 4/5/20) is a single JSON override of
 * DeliveryService::expectedBattingState()'s normal Delivery-derived
 * computation, used only by non-delivery scoring events (Change Strike,
 * Retired Hurt/Out) that need to correct or vacate a batting end without
 * creating a Delivery row. It is always cleared the next time a Delivery
 * is actually recorded, so it can never silently persist as stale state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('innings', function (Blueprint $table) {
            $table->enum('completion_type', ['automatic', 'manual'])->nullable()->after('status');
            $table->text('completion_reason')->nullable()->after('completion_type');
            $table->json('pending_state')->nullable()->after('extras');
        });

        $this->backfillCompletionType();
    }

    public function down(): void
    {
        Schema::table('innings', function (Blueprint $table) {
            $table->dropColumn(['completion_type', 'completion_reason', 'pending_state']);
        });
    }

    /**
     * Every already-completed innings predates this column and must not
     * simply default to null/manual — undoLastDelivery() now trusts
     * completion_type as the sole source of truth for whether undo may
     * reopen it, replacing the old closed-loop re-evaluation. This
     * backfill runs that exact same automatic-completion condition
     * (>=10 wickets, overs limit reached, or — innings #2 only — target
     * reached) once, here, so every pre-existing completed innings keeps
     * the same undo eligibility it already had.
     */
    private function backfillCompletionType(): void
    {
        $completedInnings = DB::table('innings')->where('status', 'completed')->get();

        foreach ($completedInnings as $innings) {
            $isAutomatic = (int) $innings->total_wickets >= 10;

            if (! $isAutomatic) {
                $match = DB::table('matches')->where('id', $innings->match_id)->first();

                if ($match && $match->overs_per_innings && (int) $innings->legal_balls >= $match->overs_per_innings * 6) {
                    $isAutomatic = true;
                }
            }

            if (! $isAutomatic && (int) $innings->innings_number === 2) {
                $firstInnings = DB::table('innings')
                    ->where('match_id', $innings->match_id)
                    ->where('innings_number', 1)
                    ->first();

                if ($firstInnings && (int) $innings->total_runs >= (int) $firstInnings->total_runs + 1) {
                    $isAutomatic = true;
                }
            }

            DB::table('innings')->where('id', $innings->id)->update([
                'completion_type' => $isAutomatic ? 'automatic' : 'manual',
            ]);
        }
    }
};
