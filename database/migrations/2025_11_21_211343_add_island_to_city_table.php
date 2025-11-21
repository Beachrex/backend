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
        Schema::table('city', function (Blueprint $table) {
            // Add island_id to link city to island
            $table->unsignedBigInteger('island_id')->nullable()->after('region_id');
            $table->foreign('island_id')->references('id')->on('island')->onDelete('set null');

            // Add index for performance
            $table->index('island_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('city', function (Blueprint $table) {
            $table->dropForeign(['island_id']);
            $table->dropIndex(['island_id']);
            $table->dropColumn('island_id');
        });
    }
};
