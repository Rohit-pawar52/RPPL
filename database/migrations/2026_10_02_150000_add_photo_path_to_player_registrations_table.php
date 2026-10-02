<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The photo a player uploads on the public registration form. Like the
     * payment proof it is a PRIVATE file (the 'local' disk, served only to
     * an admin through a policy-checked route): a stranger's upload must
     * not appear on the public site before anyone has looked at it, so it
     * stays separate from players.photo_path, which is public.
     */
    public function up(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->string('photo_path', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
