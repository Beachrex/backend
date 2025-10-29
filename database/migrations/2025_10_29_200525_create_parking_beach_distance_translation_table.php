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
        Schema::create('parking_beach_distance_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parking_beach_distance_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru');
            $table->string('name')->comment('Translated parking beach distance name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('parking_beach_distance_id')->references('id')->on('parking_beach_distance')->onDelete('cascade');

            // Unique constraint - one translation per locale per parking beach distance
            $table->unique(['parking_beach_distance_id', 'locale'], 'parking_beach_distance_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_beach_distance_translation');
    }
};
