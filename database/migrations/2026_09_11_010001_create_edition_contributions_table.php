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
        Schema::create('edition_contributions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')->constrained('editions')->restrictOnDelete();
            $table->foreignId('committee_member_id')->constrained('committee_members')->restrictOnDelete();
            $table->foreignId('edition_transaction_id')->constrained('edition_transactions')->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('contributed_at');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['edition_id', 'committee_member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edition_contributions');
    }
};
