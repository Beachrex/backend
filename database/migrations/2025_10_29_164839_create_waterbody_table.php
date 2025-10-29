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
        Schema::create('waterbody', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // e.g., 'sea', 'lake', 'river', 'pool'

            // Foreign key to status table
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->foreign('status_id')->references('id')->on('status');

            // Whether to show in filters
            $table->boolean('show_filter')->default(true);

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
        Schema::dropIfExists('waterbody');
    }
};
