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
        Schema::create('road_to_parking_type_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('road_to_parking_type_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq');
            $table->string('name')->comment('Translated road to parking type name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('road_to_parking_type_id')->references('id')->on('road_to_parking_type')->onDelete('cascade');

            // Unique constraint - one translation per locale per road to parking type
            $table->unique(['road_to_parking_type_id', 'locale'], 'road_to_parking_type_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('road_to_parking_type_translation');
    }
};
