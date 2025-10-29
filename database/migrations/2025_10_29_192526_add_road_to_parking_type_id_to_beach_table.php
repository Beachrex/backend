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
            $table->unsignedBigInteger('road_to_parking_type_id')->nullable()->after('accessibility_id');
            $table->foreign('road_to_parking_type_id')->references('id')->on('road_to_parking_type')->nullOnDelete();
            $table->index('road_to_parking_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['road_to_parking_type_id']);
            $table->dropIndex(['road_to_parking_type_id']);
            $table->dropColumn('road_to_parking_type_id');
        });
    }
};
