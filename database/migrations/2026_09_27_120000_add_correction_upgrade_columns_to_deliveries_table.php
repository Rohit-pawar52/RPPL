<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 rules 42-53 — additive columns only, nothing rewritten on
 * existing rows (action_sequence is backfilled by the dedicated
 * 2026_09_27_120002 migration, once both this and the scoring_events
 * sibling column exist).
 *
 * idempotency_key (rule 51): the client-generated token for one quick-
 * scoring tap. Unique per innings (not globally) so the same UUID
 * accidentally reused across two different innings — practically
 * impossible with a real UUID, but never assumed — cannot collide.
 * Nullable: every pre-existing Delivery and any direct (non-HTTP)
 * caller that never supplies one simply has no idempotency protection,
 * exactly as before this phase.
 *
 * action_sequence (rule 42): a single strictly-increasing ordering key
 * shared with scoring_events.action_sequence, so Universal Undo can
 * determine "the chronologically latest reversible action" across both
 * tables without relying on timestamp precision (this app's timestamp
 * columns are second-precision, and two different admin actions on a
 * fast match-day UI can legitimately land in the same second).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('commentary');
            $table->unsignedBigInteger('action_sequence')->nullable()->after('idempotency_key');

            $table->unique(['innings_id', 'idempotency_key']);
            $table->index(['innings_id', 'action_sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique(['innings_id', 'idempotency_key']);
            $table->dropIndex(['innings_id', 'action_sequence']);
            $table->dropColumn(['idempotency_key', 'action_sequence']);
        });
    }
};
