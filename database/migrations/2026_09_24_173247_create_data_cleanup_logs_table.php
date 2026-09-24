<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.49 — a reusable audit trail for every destructive Data
 * Cleanup action, across every tab/category. Deliberately generic
 * columns (category/action/criteria) rather than one table per cleanup
 * type, since every cleanup action shares the exact same "who / what /
 * when / how much" shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_cleanup_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->string('category', 50);
            $table->string('action', 100);
            // Structured, safe criteria only (e.g. {"before_date":
            // "2026-09-23", "timezone": "Asia/Kolkata"} or {"edition_id":
            // 3, "document_type": "aadhaar"}) — never raw document
            // contents, filenames, or other PII.
            $table->json('criteria')->nullable();
            $table->unsignedInteger('records_affected')->default(0);
            // Null when the category never deletes files (e.g.
            // notifications/FCM tokens) — distinct from "0 files
            // deleted", which means files were expected but none found.
            $table->unsignedInteger('files_deleted')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_cleanup_logs');
    }
};
