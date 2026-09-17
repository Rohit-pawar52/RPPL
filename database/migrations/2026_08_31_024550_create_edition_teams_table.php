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
        Schema::create('edition_teams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')
                ->constrained('editions')
                ->cascadeOnDelete();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['edition_id', 'team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edition_teams');
    }
};
