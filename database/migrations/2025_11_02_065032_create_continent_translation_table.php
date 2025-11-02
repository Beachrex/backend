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
        Schema::create('continent_translation', function (Blueprint $table) {
            $table->id();
            $table->string('continent_code', 2);
            $table->string('locale', 2)->comment('Language code (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)');
            $table->string('name')->comment('Translated continent name');
            $table->string('slug')->comment('URL-friendly slug for this language');
            $table->timestamps();

            // Foreign keys
            $table->foreign('continent_code')->references('code')->on('continents')->cascadeOnDelete();

            // Indexes
            $table->unique(['continent_code', 'locale']);
            $table->index('locale');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('continent_translation');
    }
};
