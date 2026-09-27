<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 — additive columns only.
 *
 * Deliberately does NOT touch the existing result_type enum (no
 * 'super_over' value added to it): a super-over-decided match is still,
 * correctly, a 'won' match — result_source distinguishes HOW the win was
 * reached without widening the enum, which would require doctrine/dbal
 * to alter portably across this project's MySQL (real) and SQLite
 * (test) connections. result_note carries the mandatory reason/note for
 * a super over result now, and doubles as the same mandatory-reason
 * field for a reopened match's audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('result_source')->nullable()->after('result_type');
            $table->text('result_note')->nullable()->after('result_source');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['result_source', 'result_note']);
        });
    }
};
