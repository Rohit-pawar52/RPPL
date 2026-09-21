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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // group+key together form the dotted allow-listed setting name
            // (e.g. "general.application_name") that SettingsRegistry/
            // SettingsService use — never a single combined column, so an
            // admin UI can group/tab by `group` with a plain query.
            $table->string('group', 50);
            $table->string('key', 100);

            // One generic nullable column for every supported type (see
            // Setting::TYPES) — TEXT, not VARCHAR, since an `encrypted`
            // value (Laravel's Crypt::encryptString() ciphertext) is far
            // longer than a typical string/color/path value and this
            // column must comfortably fit either. Never cast at the model
            // level — SettingsService alone decides how to interpret this
            // per the row's own `type` column.
            $table->text('value')->nullable();
            $table->string('type', 20);

            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
