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
        Schema::create('gender_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('gender_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated gender name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('gender_id')->references('id')->on('gender')->onDelete('cascade');

            // Unique constraint - one translation per locale per gender
            $table->unique(['gender_id', 'locale']);

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gender_translation');
    }
};
