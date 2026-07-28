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
        Schema::create('culinary_offer_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('culinary_offer_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq');
            $table->string('name')->comment('Translated culinary offer name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign keys
            $table->foreign('culinary_offer_id')->references('id')->on('culinary_offer')->onDelete('cascade');

            // Indexes
            $table->index('culinary_offer_id');
            $table->index('locale');

            // Unique constraint
            $table->unique(['culinary_offer_id', 'locale'], 'culinary_offer_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culinary_offer_translation');
    }
};
