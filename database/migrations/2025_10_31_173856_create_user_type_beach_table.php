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
        Schema::create('user_type_beach', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->unsignedBigInteger('user_type_id');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
            $table->foreign('user_type_id')->references('id')->on('user_type')->onDelete('cascade');
            $table->foreign('status_id')->references('id')->on('status');
            $table->unique(['beach_id', 'user_type_id'], 'beach_user_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_type_beach');
    }
};
