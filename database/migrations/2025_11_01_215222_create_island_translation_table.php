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
        Schema::create('island_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('island_id');
            $table->string('locale', 2)->comment('Language code (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)');
            $table->string('name')->comment('Translated island name');
            $table->string('slug')->comment('URL-friendly slug for this language');
            $table->timestamps();

            // Foreign keys
            $table->foreign('island_id')->references('id')->on('island')->cascadeOnDelete();

            // Indexes
            $table->unique(['island_id', 'locale']);
            $table->index('locale');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('island_translation');
    }
};
