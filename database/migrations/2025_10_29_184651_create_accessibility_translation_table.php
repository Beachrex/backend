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
        Schema::create('accessibility_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accessibility_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru');
            $table->string('name')->comment('Translated accessibility name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign key
            $table->foreign('accessibility_id')->references('id')->on('accessibility')->onDelete('cascade');

            // Unique constraint - one translation per locale per accessibility
            $table->unique(['accessibility_id', 'locale']);

            // Index for locale lookups
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessibility_translation');
    }
};
