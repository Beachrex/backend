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
        Schema::create('region_translation', function (Blueprint $table) {
            $table->id();

            // Foreign key to region table
            $table->foreignId('region_id')
                ->constrained('region')
                ->cascadeOnDelete();

            $table->string('locale', 5)->comment('Language code (e.g., en, sl, de)');
            $table->string('name', 255)->comment('Translated region name');
            $table->string('slug', 255)->nullable()->comment('URL-friendly slug');

            $table->timestamps();

            // Unique constraint: one translation per region per locale
            $table->unique(['region_id', 'locale']);
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('region_translation');
    }
};
