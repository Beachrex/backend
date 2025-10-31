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
        Schema::create('sporting_activities_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sporting_activities_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated activity name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            $table->foreign('sporting_activities_id')->references('id')->on('sporting_activities')->onDelete('cascade');
            $table->unique(['sporting_activities_id', 'locale'], 'sporting_activities_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sporting_activities_translation');
    }
};
