<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle foundation: adds is_active to the four MASTER/reusable
 * entities in the schema (users, players, teams, venues). Every other
 * table was deliberately left untouched — see the Phase 3.3.5 report
 * for the full per-table audit:
 *   - editions / matches / innings already carry their own
 *     domain-specific status enum.
 *   - player_registrations already carries payment_status.
 *   - edition_teams / team_players / match_players are pivot/context
 *     rows whose lifecycle comes from their parent record.
 *   - deliveries is historical scoring data, not selectable master data.
 *
 * default(true) means every existing row is safely backfilled to
 * is_active = true by the database itself when the column is added —
 * no manual UPDATE statement is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role_id');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('user_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('logo_path');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
