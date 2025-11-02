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
        Schema::create('country_translation', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->comment('ISO 3166-1 alpha-2 country code');
            $table->string('locale', 2)->comment('Language code (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)');
            $table->string('name')->comment('Translated country name');
            $table->string('full_name')->nullable()->comment('Translated full country name');
            $table->string('slug')->comment('URL-friendly slug for this language');
            $table->timestamps();

            // Foreign key to countries table
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();

            // Unique constraint: one translation per country per language
            $table->unique(['country_code', 'locale']);

            // Indexes for performance
            $table->index('locale');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_translation');
    }
};
