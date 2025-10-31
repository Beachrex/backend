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
        Schema::create('user_type_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_type_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated user type name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            $table->foreign('user_type_id')->references('id')->on('user_type')->onDelete('cascade');
            $table->unique(['user_type_id', 'locale'], 'user_type_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_type_translation');
    }
};
