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
        Schema::create('recommended_transport_type_translation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recommended_transport_type_id');
            $table->string('locale', 5)->comment('Language code: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk');
            $table->string('name')->comment('Translated transport type name');
            $table->string('slug')->nullable()->comment('URL-friendly slug');
            $table->timestamps();

            $table->foreign('recommended_transport_type_id')->references('id')->on('recommended_transport_type')->onDelete('cascade');
            $table->unique(['recommended_transport_type_id', 'locale'], 'recommended_transport_type_locale_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recommended_transport_type_translation');
    }
};
