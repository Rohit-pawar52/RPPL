<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sponsor ads shown on the public site. The tier decides where an ad
     * appears (main = fixed top slot, normal = rotating banner, mini =
     * logo strip at the bottom); the admin never picks a position.
     * starts_on / ends_on are plain calendar dates (display timezone),
     * both optional.
     */
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('tier', 10);
            $table->string('media_type', 10);
            $table->string('media_path');
            $table->string('poster_path')->nullable();
            $table->string('status', 10)->default('active');
            $table->unsignedTinyInteger('weight')->default(1);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['status', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
