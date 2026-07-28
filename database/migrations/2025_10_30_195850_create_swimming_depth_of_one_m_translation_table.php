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
        Schema::create('swimming_depth_of_one_m_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('swimming_depth_of_one_m_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq');
            $table->string('name')->comment('Translated swimming depth name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('swimming_depth_of_one_m_id', 'swimming_depth_fk')->references('id')->on('swimming_depth_of_one_m')->onDelete('cascade');

            // Unique constraint - one translation per locale per swimming depth
            $table->unique(['swimming_depth_of_one_m_id', 'locale'], 'swimming_depth_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('swimming_depth_of_one_m_translation');
    }
};
