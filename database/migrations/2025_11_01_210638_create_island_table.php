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
        Schema::create('island', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Island name (proper noun, no translation needed)');
            $table->string('slug')->unique()->comment('URL-friendly slug');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('status');
            $table->timestamps();

            // Indexes
            $table->index('status_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('island');
    }
};
