<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Purely additive: existing editions default to registration_open =
     * false (admin must explicitly open registration for each edition)
     * and registration_fee = null (unset until the admin configures it).
     * Neither column changes Edition::openForParticipation()'s existing
     * meaning (status != completed) — that scope keeps governing broader
     * admin participation eligibility; registration_open is a separate,
     * narrower gate for the future public registration form only.
     */
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->boolean('registration_open')->default(false)->after('status');
            $table->decimal('registration_fee', 10, 2)->nullable()->after('registration_open');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table) {
            $table->dropColumn(['registration_open', 'registration_fee']);
        });
    }
};
