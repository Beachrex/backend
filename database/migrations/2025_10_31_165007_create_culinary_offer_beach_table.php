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
        Schema::create('culinary_offer_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('culinary_offer_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('culinary_offer_id')->references('id')->on('culinary_offer')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');

            // Indexes
            $table->index('beach_id');
            $table->index('culinary_offer_id');
            $table->index('status_id');

            // Unique constraint to prevent duplicate entries
            $table->unique(['beach_id', 'culinary_offer_id'], 'beach_culinary_offer_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culinary_offer_beach');
    }
};
