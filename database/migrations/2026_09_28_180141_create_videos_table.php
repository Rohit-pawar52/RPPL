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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('status')->default('active');
            // Admin-controlled manual ordering (smaller = earlier, shown
            // first) — no drag-and-drop, just a plain integer. Not unique:
            // several videos may share a priority, in which case newest
            // (highest id) among them wins as the deterministic tiebreak.
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index(['status', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
