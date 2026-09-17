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
        Schema::create('contributors', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('phone', 20)->nullable();

            // Explicit, admin-set identity link only — never inferred by
            // name/phone matching. Unique when set: one CommitteeMember
            // identity must never be linked to more than one Contributor
            // record. Nullable: most general contributors have no
            // committee identity at all.
            $table->foreignId('committee_member_id')->nullable()->unique()->constrained('committee_members')->restrictOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributors');
    }
};
