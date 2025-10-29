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
        Schema::create('parking_capacity_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parking_capacity_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru');
            $table->string('name')->comment('Translated parking capacity name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('parking_capacity_id')->references('id')->on('parking_capacity')->onDelete('cascade');

            // Unique constraint - one translation per locale per parking capacity
            $table->unique(['parking_capacity_id', 'locale'], 'parking_capacity_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parking_capacity_translation');
    }
};
