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
        Schema::create('media_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Unique identifier: image, video, sphere_360, icon, banner, document');
            $table->string('name', 100)->comment('Display name');
            $table->json('mime_types')->nullable()->comment('Allowed MIME types array');
            $table->unsignedBigInteger('max_file_size')->nullable()->comment('Maximum file size in bytes');
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
        Schema::dropIfExists('media_types');
    }
};

