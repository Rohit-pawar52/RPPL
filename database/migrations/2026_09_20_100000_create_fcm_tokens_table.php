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
        Schema::create('fcm_tokens', function (Blueprint $table) {
            $table->id();

            // Opaque Firebase registration token. UNIQUE is what makes
            // guest re-subscription safe under concurrency (see
            // FcmTokenSubscriptionService) without any application-level
            // locking.
            $table->string('token', 512)->unique();

            // Both nullable, both null for every V1 guest subscription —
            // mirrors edition_contributions' committee_member_id/
            // contributor_id shape (two nullable identity FKs) rather
            // than a polymorphic owner column. Not enforced as
            // "exactly one" at the DB level yet — nothing sets either
            // column in this phase.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fcm_tokens');
    }
};
