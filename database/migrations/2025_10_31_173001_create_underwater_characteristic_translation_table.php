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
        Schema::create('underwater_characteristic_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('underwater_characteristic_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq');
            $table->string('name')->comment('Translated characteristic name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            $table->foreign('underwater_characteristic_id')->references('id')->on('underwater_characteristic')->onDelete('cascade');
            $table->unique(['underwater_characteristic_id', 'locale'], 'underwater_characteristic_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('underwater_characteristic_translation');
    }
};
