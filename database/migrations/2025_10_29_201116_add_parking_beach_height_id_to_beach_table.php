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
        Schema::table('beach', function (Blueprint $table) {
            $table->unsignedBigInteger('parking_beach_height_id')->nullable()->after('parking_beach_distance_id');
            $table->foreign('parking_beach_height_id')->references('id')->on('parking_beach_height')->nullOnDelete();
            $table->index('parking_beach_height_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['parking_beach_height_id']);
            $table->dropIndex(['parking_beach_height_id']);
            $table->dropColumn('parking_beach_height_id');
        });
    }
};
