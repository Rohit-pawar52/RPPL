<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Why an admin marked a payment as failed (e.g. "UTR not found in the
     * bank statement"). It is shown to the player on the public status page
     * so they know what to fix, and is only ever kept while the payment
     * status is 'failed'.
     */
    public function up(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->string('payment_failure_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->dropColumn('payment_failure_reason');
        });
    }
};
