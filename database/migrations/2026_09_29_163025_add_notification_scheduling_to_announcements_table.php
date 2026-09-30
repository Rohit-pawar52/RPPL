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
        Schema::table('announcements', function (Blueprint $table) {
            // Whether THIS announcement should also push a Firebase
            // notification — entirely separate from starts_at/ends_at,
            // which only ever control public ticker VISIBILITY. Default
            // false so every existing announcement stays exactly as it
            // is: no retroactive notifications.
            $table->boolean('notification_enabled')->default(false)->after('sort_order');

            // Null means "send now" (immediately due, no future wait) —
            // a real future timestamp means "wait until this UTC instant
            // is due", scanned by the scheduler. Never derived from
            // starts_at: an admin may want the ticker visible earlier
            // than the push fires, or vice versa.
            $table->timestamp('notification_scheduled_at')->nullable()->after('notification_enabled');

            // Set ONLY once SendNotificationJob has actually been queued
            // successfully (never just because a dispatch attempt was
            // made) — see AnnouncementNotificationService::dispatchIfDue().
            // Its presence is the single source of truth for "has this
            // announcement's notification already fired", so editing the
            // announcement afterwards can never accidentally resend it.
            $table->timestamp('notification_dispatched_at')->nullable()->after('notification_scheduled_at');

            // Links to the Notification (title/message/action_url
            // snapshot vehicle) actually created and sent for this
            // announcement — null until dispatched. Reuses the existing
            // Notification/NotificationSend pipeline rather than
            // duplicating Firebase-send logic here.
            $table->foreignId('notification_id')->nullable()->after('notification_dispatched_at')
                ->constrained('notifications')->nullOnDelete();

            $table->index(['notification_enabled', 'notification_scheduled_at', 'notification_dispatched_at'], 'announcements_notification_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex('announcements_notification_due_index');
            $table->dropConstrainedForeignId('notification_id');
            $table->dropColumn(['notification_enabled', 'notification_scheduled_at', 'notification_dispatched_at']);
        });
    }
};
