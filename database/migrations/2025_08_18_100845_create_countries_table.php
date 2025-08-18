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
        Schema::create('countries', function (Blueprint $table) {
            $table->string('code', 2)->primary(); // e.g., "SI"
            $table->string('name', 100);
            $table->string('full_name', 255)->nullable();
            $table->string('iso3', 3)->nullable();
            $table->string('number', 3)->nullable();
            $table->string('continent_code', 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
