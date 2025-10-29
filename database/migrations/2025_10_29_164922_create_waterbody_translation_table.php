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
        Schema::create('waterbody_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('waterbody_id');
            $table->string('locale', 5); // e.g., en, sl, de, hr, fr, es, it, ua, ru

            $table->string('name'); // Translated name
            $table->string('slug')->nullable(); // URL-friendly version

            $table->timestamps();

            // Unique constraint: one translation per waterbody per locale
            $table->unique(['waterbody_id', 'locale']);

            // Foreign key
            $table->foreign('waterbody_id')
                ->references('id')
                ->on('waterbody')
                ->onDelete('cascade');

            // Indexes
            $table->index('locale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waterbody_translation');
    }
};
