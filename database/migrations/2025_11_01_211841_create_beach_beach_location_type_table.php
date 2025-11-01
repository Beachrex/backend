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
        Schema::create('beach_beach_location_type', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('beach_location_type_id');
            $table->timestamps();

            $table->foreign('beach_id')->references('id')->on('beach')->cascadeOnDelete();
            $table->foreign('beach_location_type_id')->references('id')->on('beach_location_type')->cascadeOnDelete();

            $table->unique(['beach_id', 'beach_location_type_id'], 'beach_location_type_unique');
            $table->index('beach_id');
            $table->index('beach_location_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_beach_location_type');
    }
};
