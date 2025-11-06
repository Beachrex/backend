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
        Schema::create('user_migration_status', function (Blueprint $table) {
            $table->id();
            $table->string('old_user_id')->unique();
            $table->unsignedBigInteger('new_user_id')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed, skipped
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('new_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_migration_status');
    }
};
