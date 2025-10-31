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
        Schema::create('natural_location_condition_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('natural_location_condition_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('natural_location_condition_id')->references('id')->on('natural_location_condition')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');
            $table->unique(['beach_id', 'natural_location_condition_id'], 'beach_natural_location_condition_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('natural_location_condition_beach');
    }
};
