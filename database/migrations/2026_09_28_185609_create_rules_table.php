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
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_type_id')->constrained('rule_types')->restrictOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('image_path')->nullable();
            // Ordering within the parent rule type only (not globally
            // meaningful) — same convention as rule_types.sort_order.
            $table->unsignedInteger('sort_order')->default(100);
            $table->string('status')->default('active');
            $table->boolean('is_important')->default(false);
            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
            $table->index(['rule_type_id', 'status', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
