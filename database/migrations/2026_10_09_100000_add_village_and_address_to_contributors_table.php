<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contributor was only a name and an optional phone, so two people called "Ramesh Patil" could not be
     * told apart in a list, on a receipt or in the ranking. Village is the short, everyday way to tell them
     * apart (same word the venues and player registrations already use); address is the free-text rest
     * (tehsil, district, landmark). Both are nullable: every contributor recorded before this keeps working
     * with no village, and the admin can fill it in from the Edit page whenever it is known.
     */
    public function up(): void
    {
        Schema::table('contributors', function (Blueprint $table) {
            $table->string('village', 100)->nullable()->after('phone');
            $table->string('address', 255)->nullable()->after('village');
        });
    }

    public function down(): void
    {
        Schema::table('contributors', function (Blueprint $table) {
            $table->dropColumn(['village', 'address']);
        });
    }
};
