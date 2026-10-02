<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a player typed into the (Google) registration form for THIS
     * edition, kept on the registration rather than the player: the guest
     * flow never edits an existing Player, and a returning player may answer
     * differently next season. Everything is nullable, so existing rows,
     * seeders, the public form and admin-created registrations are
     * untouched.
     *
     * photo_url / payment_proof_url hold the Google Drive link a form
     * export gives for an uploaded file — the file itself can't travel in a
     * CSV, so the link is what lets an admin open the original. They are
     * deliberately separate from payment_proof_path (a file we store
     * ourselves).
     *
     * submitted_utr is the transaction id the player typed; payment_reference
     * stays the admin-verified value.
     */
    public function up(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('village', 100)->nullable();
            $table->string('tehsil', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('submitted_utr', 100)->nullable();
            $table->string('photo_url', 512)->nullable();
            $table->string('payment_proof_url', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'age',
                'village',
                'tehsil',
                'district',
                'submitted_utr',
                'photo_url',
                'payment_proof_url',
            ]);
        });
    }
};
