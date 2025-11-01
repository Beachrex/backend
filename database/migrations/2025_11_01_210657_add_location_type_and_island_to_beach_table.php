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
            $table->unsignedBigInteger('beach_location_type_id')->nullable()->after('occupancy_rate_id');
            $table->foreign('beach_location_type_id')->references('id')->on('beach_location_type')->nullOnDelete();
            $table->index('beach_location_type_id');

            $table->unsignedBigInteger('water_body_type_id')->nullable()->after('beach_location_type_id');
            $table->foreign('water_body_type_id')->references('id')->on('water_body_type')->nullOnDelete();
            $table->index('water_body_type_id');

            $table->unsignedBigInteger('island_id')->nullable()->after('water_body_type_id');
            $table->foreign('island_id')->references('id')->on('island')->nullOnDelete();
            $table->index('island_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropForeign(['beach_location_type_id']);
            $table->dropIndex(['beach_location_type_id']);
            $table->dropColumn('beach_location_type_id');

            $table->dropForeign(['water_body_type_id']);
            $table->dropIndex(['water_body_type_id']);
            $table->dropColumn('water_body_type_id');

            $table->dropForeign(['island_id']);
            $table->dropIndex(['island_id']);
            $table->dropColumn('island_id');
        });
    }
};
