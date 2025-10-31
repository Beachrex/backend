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
        Schema::create('underwater_characteristic_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('underwater_characteristic_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('underwater_characteristic_id')->references('id')->on('underwater_characteristic')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');
            $table->unique(['beach_id', 'underwater_characteristic_id'], 'beach_underwater_characteristic_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('underwater_characteristic_beach');
    }
};
