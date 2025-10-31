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
        Schema::create('location_infrastructure_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('location_infrastructure_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('location_infrastructure_id')->references('id')->on('location_infrastructure')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');

            // Indexes
            $table->index('beach_id');
            $table->index('location_infrastructure_id');
            $table->index('status_id');

            // Unique constraint to prevent duplicate entries
            $table->unique(['beach_id', 'location_infrastructure_id'], 'beach_location_infrastructure_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_infrastructure_beach');
    }
};
