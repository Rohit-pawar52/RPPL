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
        Schema::create('player_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')
                ->constrained('editions')
                ->cascadeOnDelete();

            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->enum('payment_status', [
                'pending',
                'paid',
                'failed',
                'refunded',
            ])->default('pending');

            $table->decimal('registration_fee', 10, 2)->nullable();

            $table->timestamp('registered_at')->nullable();

            $table->timestamps();

            $table->unique(['edition_id', 'player_id']);
            $table->index(['edition_id', 'payment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_registrations');
    }
};
