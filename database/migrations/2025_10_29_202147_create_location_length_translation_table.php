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
        Schema::create('location_length_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('location_length_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated location length name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('location_length_id')->references('id')->on('location_length')->onDelete('cascade');

            // Unique constraint - one translation per locale per location length
            $table->unique(['location_length_id', 'locale'], 'location_length_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_length_translation');
    }
};

