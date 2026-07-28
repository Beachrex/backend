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
        Schema::create('badge_translation', function (Blueprint $table) {
            $table->id();

            // Foreign key to badge table
            $table->foreignId('badge_id')
                ->constrained('badge')
                ->cascadeOnDelete();
            $table->index('badge_id');

            // Locale for translation (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq)
            $table->string('locale', 5);
            $table->index('locale');

            // Translated fields
            $table->string('name', 255);
            $table->string('slug', 255)->nullable();

            // Unique constraint: one translation per badge per locale
            $table->unique(['badge_id', 'locale']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_translation');
    }
};
