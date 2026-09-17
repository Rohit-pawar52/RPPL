<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->year('year')->unique();

            $table->enum('status', [
                'upcoming',
                'active',
                'completed',
            ])->default('upcoming');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};
