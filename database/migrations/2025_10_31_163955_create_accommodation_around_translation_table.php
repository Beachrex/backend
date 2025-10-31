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
        Schema::create('accommodation_around_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accommodation_around_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated accommodation type name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('accommodation_around_id')->references('id')->on('accommodation_around')->onDelete('cascade');

            // Unique constraint - one translation per locale per accommodation type
            $table->unique(['accommodation_around_id', 'locale'], 'accommodation_around_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodation_around_translation');
    }
};
