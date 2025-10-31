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
        Schema::create('sporting_activities_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('sporting_activities_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('sporting_activities_id')->references('id')->on('sporting_activities')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');
            $table->unique(['beach_id', 'sporting_activities_id'], 'beach_sporting_activities_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sporting_activities_beach');
    }
};
