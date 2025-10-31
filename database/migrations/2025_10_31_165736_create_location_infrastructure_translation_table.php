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
        Schema::create('location_infrastructure_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('location_infrastructure_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated infrastructure name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            // Foreign keys
            $table->foreign('location_infrastructure_id')->references('id')->on('location_infrastructure')->onDelete('cascade');

            // Indexes
            $table->index('location_infrastructure_id');
            $table->index('locale');

            // Unique constraint
            $table->unique(['location_infrastructure_id', 'locale'], 'location_infrastructure_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_infrastructure_translation');
    }
};
