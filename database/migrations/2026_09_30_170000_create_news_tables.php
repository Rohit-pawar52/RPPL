<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // Generated from the title once, then kept stable on edit so
            // shared links don't break.
            $table->string('slug')->unique();
            $table->mediumText('content');
            $table->string('status')->default('active');
            // Same convention as videos/photos: smaller = shown first.
            $table->unsignedInteger('priority')->default(100);
            // Public only once this moment has passed (and status is active).
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('news_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['news_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_images');
        Schema::dropIfExists('news');
    }
};
