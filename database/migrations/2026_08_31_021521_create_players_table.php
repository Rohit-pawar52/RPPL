<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();

            // Optional login account
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();

            // Player profile
            $table->string('name');
            $table->string('phone', 20)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('photo_path', 512)->nullable();

            // Cricket information
            $table->enum('batting_style', [
                'right_hand',
                'left_hand',
            ])->nullable();

            $table->enum('bowling_style', [
                'right_arm',
                'left_arm',
                'none',
            ])->nullable();

            $table->enum('primary_role', [
                'batter',
                'bowler',
                'all_rounder',
                'wicket_keeper',
            ])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
