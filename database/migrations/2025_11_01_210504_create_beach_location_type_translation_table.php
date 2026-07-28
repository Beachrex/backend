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
        Schema::create('beach_location_type_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_location_type_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq');
            $table->string('name')->comment('Translated beach location type name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('beach_location_type_id')->references('id')->on('beach_location_type')->onDelete('cascade');

            // Unique constraint - one translation per locale per beach location type
            $table->unique(['beach_location_type_id', 'locale']);

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_location_type_translation');
    }
};
