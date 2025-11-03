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
        Schema::create('article_translation', function (Blueprint $table) {
            $table->id();

            // Foreign key to article table
            $table->foreignId('article_id')
                ->constrained('article')
                ->cascadeOnDelete();
            $table->index('article_id');

            // Locale for translation (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)
            $table->string('locale', 5);
            $table->index('locale');

            // Translated fields
            $table->string('title', 255);
            $table->string('slug', 255)->nullable();
            $table->text('excerpt')->nullable()->comment('Short description/summary');
            $table->text('content')->nullable()->comment('Full article content');

            // Unique constraint: one translation per article per locale
            $table->unique(['article_id', 'locale']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_translation');
    }
};
