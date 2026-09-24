<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3.48 — edition-specific committee membership. Being on the
 * committee is no longer a separate person identity (CommitteeMember);
 * it is a per-edition membership row for an existing Contributor. See
 * the next migration for how existing CommitteeMember data becomes
 * Contributors + rows here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_committee_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edition_id')->constrained('editions')->restrictOnDelete();
            $table->foreignId('contributor_id')->constrained('contributors')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['edition_id', 'contributor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_committee_members');
    }
};
