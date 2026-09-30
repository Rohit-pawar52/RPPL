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
        // Idempotency log for the Tournament-Day Morning Reminder — one
        // row per (edition, display-timezone calendar date), written ONLY
        // once SendNotificationJob was actually queued (see
        // TournamentDayReminderService::dispatchIfDue()). A failed
        // dispatch leaves no row, so the next scheduler run retries.
        Schema::create('tournament_day_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edition_id')->constrained()->cascadeOnDelete();
            $table->date('notification_date');
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->timestamp('dispatched_at');
            $table->timestamps();

            $table->unique(['edition_id', 'notification_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_day_notifications');
    }
};
