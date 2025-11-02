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
        Schema::create('lake', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Lake code (e.g., bled, garda, como)');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('status');
            $table->timestamps();

            // Indexes
            $table->index('status_id');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lake');
    }
};

