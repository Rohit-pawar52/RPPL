<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('photo_path');
            $table->string('status')->default('active');
            // Same convention as videos.priority: smaller = shown first,
            // not unique, newest (highest id) wins ties.
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
