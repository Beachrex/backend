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
        Schema::create('media_collections', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('Unique identifier: beach_gallery, parking_images, promotional_banner, etc.');
            $table->string('name', 150)->comment('Display name');
            $table->text('description')->nullable()->comment('Description of collection purpose');
            $table->json('allowed_media_types')->nullable()->comment('Array of allowed media_type codes');
            $table->unsignedInteger('max_items')->nullable()->comment('Maximum number of items per entity (null = unlimited)');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->timestamps();

            $table->foreign('status_id')->references('id')->on('status');
            $table->index('status_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_collections');
    }
};

