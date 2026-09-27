<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase S02 rules 43/44/46 — append-only audit trail for the quick
 * correction window (only the latest 3 Delivery rows of an innings may
 * ever be corrected — see DeliveryService::correctDelivery()). Mirrors
 * ScoringEvent's own append-only pattern (no updated_at, never mutated
 * after creation): a correction never overwrites or discards the
 * before/after facts, so "what did this used to say" is always
 * reconstructible later, even though the Delivery row itself is edited
 * in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_corrections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('delivery_id')
                ->constrained('deliveries')
                ->cascadeOnDelete();

            $table->foreignId('innings_id')
                ->constrained('innings')
                ->cascadeOnDelete();

            $table->foreignId('match_id')
                ->constrained('matches')
                ->cascadeOnDelete();

            $table->json('old_values');
            $table->json('new_values');
            $table->text('reason')->nullable();

            $table->foreignId('performed_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['delivery_id']);
            $table->index(['innings_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_corrections');
    }
};
