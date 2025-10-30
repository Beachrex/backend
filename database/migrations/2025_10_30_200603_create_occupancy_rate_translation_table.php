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
        Schema::create('occupancy_rate_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('occupancy_rate_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated occupancy rate name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('occupancy_rate_id')->references('id')->on('occupancy_rate')->onDelete('cascade');

            // Unique constraint - one translation per locale per occupancy rate
            $table->unique(['occupancy_rate_id', 'locale'], 'occupancy_rate_locale_unique');

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occupancy_rate_translation');
    }
};
