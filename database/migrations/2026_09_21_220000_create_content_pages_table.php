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
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();

            // One row per system-defined page type (see
            // App\Models\ContentPage::TYPES) — never an arbitrary,
            // admin-creatable page. The unique constraint is what makes
            // "exactly one record per canonical type" a database
            // guarantee, not just an application convention.
            $table->string('type', 50)->unique();
            $table->string('title', 150);
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_pages');
    }
};
