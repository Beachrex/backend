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
        Schema::create('article_island', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')
                ->constrained('article')
                ->cascadeOnDelete();
            $table->index('article_id');

            $table->foreignId('island_id')
                ->constrained('island')
                ->cascadeOnDelete();
            $table->index('island_id');

            // Unique constraint: one article can be attached to an island only once
            $table->unique(['article_id', 'island_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_island');
    }
};
