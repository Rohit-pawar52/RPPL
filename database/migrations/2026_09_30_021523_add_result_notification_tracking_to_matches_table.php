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
            // Deliberately separate from the existing notification_id/
            // reminder_dispatched_at pair (Match Reminder feature) — that
            // pair means "the pre-match reminder push"; these mean "the
            // post-finalization result push". Overloading the reminder's
            // own fields would make it impossible to tell which kind of
            // notification a match had actually sent. Set ONLY once
            // SendNotificationJob has actually been queued successfully
            // (never just because a dispatch attempt was made) — see
            // MatchResultNotificationService::dispatchIfDue().
            $table->timestamp('result_notification_dispatched_at')->nullable()->after('notification_id');

            $table->foreignId('result_notification_id')->nullable()->after('result_notification_dispatched_at')
                ->constrained('notifications')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_notification_id');
            $table->dropColumn('result_notification_dispatched_at');
        });
    }
};
