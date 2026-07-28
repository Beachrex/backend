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
        Schema::create('natural_location_condition_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('natural_location_condition_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq');
            $table->string('name')->comment('Translated condition name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            $table->foreign('natural_location_condition_id')->references('id')->on('natural_location_condition')->onDelete('cascade');
            $table->unique(['natural_location_condition_id', 'locale'], 'natural_location_condition_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('natural_location_condition_translation');
    }
};
