<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update ip_address column length to accommodate SHA-256 hashes (64 chars).
     */
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->string('ip_address', 64)->nullable()->change();
        });

        Schema::table('ai_usage', function (Blueprint $table) {
            $table->string('ip_address', 64)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->change();
        });

        Schema::table('ai_usage', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->change();
        });
    }
};
