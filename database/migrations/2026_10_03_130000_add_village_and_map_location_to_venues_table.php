<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A venue is a village ground: village (gram) / tehsil / district plus
     * an exact map pin. All nullable and additive — the older city/country
     * columns are kept so existing rows still display (as a fallback).
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('village', 100)->nullable();
            $table->string('tehsil', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['village', 'tehsil', 'district', 'latitude', 'longitude']);
        });
    }
};
