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
        Schema::create('parking_beach_height_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parking_beach_height_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru');
            $table->string('name')->comment('Translated parking beach height name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('parking_beach_height_id')->references('id')->on('parking_beach_height')->onDelete('cascade');

            // Unique constraint - one translation per locale per parking beach height
            $table->unique(['parking_beach_height_id', 'locale'], 'parking_beach_height_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_beach_height_translation');
    }
};
