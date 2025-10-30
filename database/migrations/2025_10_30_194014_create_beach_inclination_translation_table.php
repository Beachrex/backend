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
        Schema::create('beach_inclination_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_inclination_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated beach inclination name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('beach_inclination_id')->references('id')->on('beach_inclination')->onDelete('cascade');

            // Unique constraint - one translation per locale per beach inclination
            $table->unique(['beach_inclination_id', 'locale'], 'beach_inclination_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_inclination_translation');
    }
};
