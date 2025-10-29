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
        Schema::create('beach_type', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Unique identifier for beach type');
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('status');
            $table->boolean('show_filter')->default(true)->comment('Show in filter UI');
            $table->timestamps();

            // Indexes
            $table->index('status_id');
            $table->index('show_filter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beach_type');
    }
};
