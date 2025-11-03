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
        Schema::create('article_country', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')
                ->constrained('article')
                ->cascadeOnDelete();
            $table->index('article_id');

            $table->string('country_code', 2);
            $table->foreign('country_code')
                ->references('code')
                ->on('countries')
                ->cascadeOnDelete();
            $table->index('country_code');

            // Unique constraint: one article can be attached to a country only once
            $table->unique(['article_id', 'country_code']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_country');
    }
};
