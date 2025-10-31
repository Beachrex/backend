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
        Schema::create('accommodation_around_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('accommodation_around_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('accommodation_around_id')->references('id')->on('accommodation_around')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');

            // Indexes
            $table->index('beach_id');
            $table->index('accommodation_around_id');
            $table->index('status_id');

            // Unique constraint - prevent duplicate accommodation types for same beach
            $table->unique(['beach_id', 'accommodation_around_id'], 'beach_accommodation_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodation_around_beach');
    }
};
