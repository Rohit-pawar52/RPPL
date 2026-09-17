<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Purely additive: every existing row keeps its populated
     * committee_member_id and a null contributor_id — no historical row
     * is rewritten. Loosening committee_member_id to nullable does not
     * touch its existing FK/restrictOnDelete behavior; MySQL/MariaDB
     * does not require dropping a foreign key to change the nullability
     * of the column it's defined on.
     */
    public function up(): void
    {
        Schema::table('edition_contributions', function (Blueprint $table) {
            $table->unsignedBigInteger('committee_member_id')->nullable()->change();

            $table->foreignId('contributor_id')->nullable()->after('committee_member_id')
                ->constrained('contributors')->restrictOnDelete();

            $table->index(['edition_id', 'contributor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('edition_contributions', function (Blueprint $table) {
            $table->dropForeign(['contributor_id']);
            $table->dropIndex(['edition_contributions_edition_id_contributor_id_index']);
            $table->dropColumn('contributor_id');

            $table->unsignedBigInteger('committee_member_id')->nullable(false)->change();
        });
    }
};
