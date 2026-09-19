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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // The editable CONTENT of an admin-authored broadcast — never
            // one immutable send event (that is notification_sends).
            // Deliberately no status column: whether/when this has been
            // sent is derived from notification_sends, never cached here.
            $table->string('title', 150);
            $table->text('message');
            $table->string('action_url')->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
