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
        Schema::create('badge_region', function (Blueprint $table) {
            $table->id();

            $table->foreignId('badge_id')
                ->constrained('badge')
                ->cascadeOnDelete();
            $table->index('badge_id');

            $table->foreignId('region_id')
                ->constrained('region')
                ->cascadeOnDelete();
            $table->index('region_id');

            // Unique constraint: one badge can be attached to a region only once
            $table->unique(['badge_id', 'region_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_region');
    }
};
