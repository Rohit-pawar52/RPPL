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
        Schema::create('edition_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')->constrained('editions')->restrictOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('category', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('transaction_date');
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['edition_id', 'type']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edition_transactions');
    }
};
