<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            // Optional window that further narrows the existing
            // registration_open switch. Null = no limit on that side, so
            // every existing edition keeps behaving exactly as before.
            $table->timestamp('registration_opens_at')->nullable()->after('registration_fee');
            $table->timestamp('registration_closes_at')->nullable()->after('registration_opens_at');

            // Due instant is derived (closes_at - minutes_before), never
            // stored, so moving the deadline before dispatch moves the
            // reminder with it — same convention as match reminders.
            $table->boolean('registration_reminder_enabled')->default(false)->after('registration_closes_at');
            $table->unsignedInteger('registration_reminder_minutes_before')->nullable()->default(1440)->after('registration_reminder_enabled');
            $table->timestamp('registration_reminder_dispatched_at')->nullable()->after('registration_reminder_minutes_before');
            $table->foreignId('registration_reminder_notification_id')->nullable()->after('registration_reminder_dispatched_at')
                ->constrained('notifications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registration_reminder_notification_id');
            $table->dropColumn([
                'registration_opens_at',
                'registration_closes_at',
                'registration_reminder_enabled',
                'registration_reminder_minutes_before',
                'registration_reminder_dispatched_at',
            ]);
        });
    }
};
