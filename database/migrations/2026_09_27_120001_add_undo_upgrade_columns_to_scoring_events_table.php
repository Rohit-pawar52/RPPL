<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 rule 42 (Universal Undo) — additive columns only.
 *
 * action_sequence: see the deliveries sibling migration's docblock —
 * the same shared ordering key, populated on this table too.
 *
 * undone_at/undone_by: ScoringEvent stays append-only (no row is ever
 * deleted or mutated in place to remove its original facts — see the
 * model's docblock) — undoing one only stamps these two columns, which
 * every aggregation that reads ScoringEvent rows for cache-rebuild
 * purposes (DeliveryService::recalculateInningsTotals(),
 * dismissedMatchPlayerIds(), ScorecardService's retirement lookup, the
 * extras-breakdown penalty sum) must now exclude via
 * ->whereNull('undone_at'). This preserves full audit history (frozen
 * rule 42.4) while still preserving who performed the undo and when
 * (frozen rule 42.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_events', function (Blueprint $table) {
            $table->unsignedBigInteger('action_sequence')->nullable()->after('id');
            $table->timestamp('undone_at')->nullable()->after('performed_by');
            $table->foreignId('undone_by')->nullable()->after('undone_at')->constrained('users')->nullOnDelete();

            $table->index(['innings_id', 'action_sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('scoring_events', function (Blueprint $table) {
            $table->dropIndex(['innings_id', 'action_sequence']);
            $table->dropConstrainedForeignId('undone_by');
            $table->dropColumn(['action_sequence', 'undone_at']);
        });
    }
};
