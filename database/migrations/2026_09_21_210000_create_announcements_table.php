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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->text('message');

            // Both nullable — see Announcement::scopeActive() for the
            // exact visibility rule each combination produces. Always
            // stored in UTC, the same as every other datetime column in
            // this app; system.display_timezone (Settings) is a DISPLAY
            // preference only and never changes what's persisted here.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->boolean('is_active')->default(true);

            // Admin-controlled manual ordering (smaller = earlier) — no
            // drag-and-drop in V1, just a plain integer.
            $table->integer('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The public ticker's one query filters on is_active plus
            // both date columns together — a single composite index
            // covers that lookup rather than three separate single-column
            // indexes.
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
