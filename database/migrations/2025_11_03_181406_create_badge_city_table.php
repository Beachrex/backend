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
        Schema::create('badge_city', function (Blueprint $table) {
            $table->id();

            $table->foreignId('badge_id')
                ->constrained('badge')
                ->cascadeOnDelete();
            $table->index('badge_id');

            $table->foreignId('city_id')
                ->constrained('city')
                ->cascadeOnDelete();
            $table->index('city_id');

            // Unique constraint: one badge can be attached to a city only once
            $table->unique(['badge_id', 'city_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_city');
    }
};
