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
        Schema::create('beach_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_slug')->index();
            $table->string('new_slug')->index();
            $table->string('locale', 5)->default('en');
            $table->unsignedBigInteger('beach_id');
            $table->timestamps();

            $table->unique(['old_slug', 'locale']);
            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_slug_redirects');
    }
};

