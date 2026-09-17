<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The backfill loop below reads/writes plain rows via the query builder
 * (not raw JOIN-UPDATE SQL) deliberately: this project's test suite runs
 * against SQLite while production runs MariaDB, and a single portable
 * PHP loop avoids needing two different dialects of a JOIN/UPDATE
 * statement (SQLite lacks MySQL's UPDATE...JOIN syntax; the two engines
 * also disagree on string concatenation/padding functions). At RPPL's
 * real scale (a few hundred registrations at most) this is negligible.
 */

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * registration_number is backfilled for every existing row using
     * ONLY its own already-unique, already-immutable primary key —
     * never created_at/COUNT/row-ordering — so historical numbering is
     * fully deterministic and reproducible: RPPL-{edition.year}-
     * {id, zero-padded to 6 digits, never truncated for larger ids}.
     * This is the exact same format PlayerRegistration::
     * assignRegistrationNumber() produces for every future row (admin,
     * CSV import, and later guest registration), so there is only ever
     * one format, generated one way.
     *
     * Safe sequence: add nullable column -> backfill every existing row
     * -> only then add the unique constraint and tighten to NOT NULL,
     * so the constraint is never checked against an incomplete backfill.
     */
    public function up(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->after('player_id');
            $table->string('aadhaar_document_path', 512)->nullable()->after('registered_at');
            $table->string('payment_proof_path', 512)->nullable()->after('aadhaar_document_path');
            $table->string('payment_reference', 100)->nullable()->after('payment_proof_path');
        });

        DB::table('player_registrations')
            ->whereNull('registration_number')
            ->orderBy('id')
            ->select('player_registrations.id', 'player_registrations.edition_id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $year = DB::table('editions')->where('id', $row->edition_id)->value('year');

                    DB::table('player_registrations')
                        ->where('id', $row->id)
                        ->update(['registration_number' => sprintf('RPPL-%d-%06d', $year, $row->id)]);
                }
            });

        Schema::table('player_registrations', function (Blueprint $table) {
            $table->string('registration_number')->nullable(false)->change();
        });

        Schema::table('player_registrations', function (Blueprint $table) {
            $table->unique('registration_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->dropUnique(['registration_number']);
            $table->dropColumn(['registration_number', 'aadhaar_document_path', 'payment_proof_path', 'payment_reference']);
        });
    }
};
