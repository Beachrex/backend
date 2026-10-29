<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time links that open the closed dev/uat websites (owner 2026-10-29). Only a SHA-256 hash of each token is
 * stored; rows older than a day are deleted when new links are requested.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preview_access_tokens')) {
            return;
        }

        Schema::create('preview_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->char('token_hash', 64)->unique();
            $table->string('return_path', 512)->default('/');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_access_tokens');
    }
};
