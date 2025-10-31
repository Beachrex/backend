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
        Schema::create('culinary_offer', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Unique identifier for culinary offer type');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            // Foreign keys
            $table->foreign('status_id')->references('id')->on('status');

            // Indexes
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('culinary_offer');
    }
};
