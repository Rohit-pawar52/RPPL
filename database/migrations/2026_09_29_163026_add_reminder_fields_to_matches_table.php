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
        Schema::table('matches', function (Blueprint $table) {
            // Default false so every existing/historical match stays
            // exactly as it is — reminders are never retroactively
            // enabled for a match an admin didn't explicitly opt in.
            $table->boolean('reminder_enabled')->default(false)->after('overs_per_innings');

            // Only meaningful when reminder_enabled is true. No separate
            // due-datetime column is stored — the due instant is always
            // DERIVED as scheduled_at - reminder_minutes_before, so
            // rescheduling the match before the reminder fires naturally
            // moves the reminder with it (see MatchReminderService).
            $table->unsignedSmallInteger('reminder_minutes_before')->nullable()->default(30)->after('reminder_enabled');

            // Set ONLY once SendNotificationJob has actually been queued
            // successfully — see MatchReminderService::dispatchIfDue().
            // Its presence is what stops a later reschedule (before OR
            // after this is set) from ever sending a second reminder.
            $table->timestamp('reminder_dispatched_at')->nullable()->after('reminder_minutes_before');

            // Same Notification-pipeline reuse as announcements.
            $table->foreignId('notification_id')->nullable()->after('reminder_dispatched_at')
                ->constrained('notifications')->nullOnDelete();

            $table->index(['reminder_enabled', 'reminder_dispatched_at'], 'matches_reminder_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropIndex('matches_reminder_due_index');
            $table->dropConstrainedForeignId('notification_id');
            $table->dropColumn(['reminder_enabled', 'reminder_minutes_before', 'reminder_dispatched_at']);
        });
    }
};
