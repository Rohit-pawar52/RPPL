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
        Schema::create('notification_sends', function (Blueprint $table) {
            $table->id();

            $table->foreignId('notification_id')->constrained('notifications')->restrictOnDelete();

            // Immutable copy of the notification's content at the moment
            // Send/Resend was clicked — a later edit to the parent
            // Notification must never rewrite what an earlier send
            // actually contained.
            $table->string('title_snapshot', 150);
            $table->text('message_snapshot');
            $table->string('action_url_snapshot')->nullable();

            $table->unsignedInteger('attempted_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();

            // Null while the queued send job hasn't finished yet — its
            // nullability alone signals "in progress" vs "done", so no
            // separate status column is needed.
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_sends');
    }
};
