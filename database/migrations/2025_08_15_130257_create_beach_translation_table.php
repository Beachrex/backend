<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBeachTranslationTable extends Migration
{
    public function up(): void
    {
        Schema::create('beach_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('beach_id');
            $table->string('locale', 5); // e.g. en, de, sl, hr

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->nullable();

            $table->timestamps();

            $table->unique(['beach_id', 'locale']);
            $table->foreign('beach_id')->references('id')->on('beach')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beach_translation');
    }
}
