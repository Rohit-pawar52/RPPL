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
        Schema::table('player_registrations', function (Blueprint $table) {
            // Advisory only — never authoritative, never admin-facing
            // as a substitute for payment_reference (see Phase B's own
            // design decision). Nullable: OCR may find nothing, or
            // Tesseract itself may fail; neither blocks registration.
            // Deliberately no unique constraint/index: duplicate
            // detection here is a simple admin-side runtime lookup
            // (PlayerRegistration::hasDuplicateOcrTransactionId()), not
            // a DB-enforced rule, and current RPPL volume doesn't
            // justify speculative indexing.
            $table->string('ocr_transaction_id', 100)->nullable()->after('payment_reference');
            $table->enum('ocr_status', ['pending', 'extracted', 'not_found', 'failed'])
                ->default('pending')->after('ocr_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->dropColumn(['ocr_transaction_id', 'ocr_status']);
        });
    }
};
