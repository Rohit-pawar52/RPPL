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
        Schema::table('contributors', function (Blueprint $table) {
            // Public profile imagery (Phase 3.40) — stored on the public
            // disk like Player photos/Team logos, never Aadhaar/payment-
            // proof private storage. Nullable: most existing and future
            // contributors will have none, and the public leaderboard
            // falls back to an initials avatar.
            $table->string('photo_path', 512)->nullable()->after('committee_member_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contributors', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
