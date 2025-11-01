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
            // Number of indoor pools
            $table->integer('indoor_pools_count')->nullable()->after('island_id');

            // Number of outdoor pools
            $table->integer('outdoor_pools_count')->nullable()->after('indoor_pools_count');

            // Indoor area in square meters
            $table->decimal('indoor_area_m2', 10, 2)->nullable()->after('outdoor_pools_count');

            // Outdoor area in square meters
            $table->decimal('outdoor_area_m2', 10, 2)->nullable()->after('indoor_area_m2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropColumn([
                'indoor_pools_count',
                'outdoor_pools_count',
                'indoor_area_m2',
                'outdoor_area_m2',
            ]);
        });
    }
};
