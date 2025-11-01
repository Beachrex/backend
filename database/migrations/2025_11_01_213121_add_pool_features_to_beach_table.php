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
            // Swimming pool
            $table->boolean('has_swimming_pool')->default(false)->after('outdoor_area_m2');

            // Swimming lanes (plavalne proge)
            $table->boolean('has_indoor_swimming_lanes')->default(false)->after('has_swimming_pool');
            $table->boolean('has_outdoor_swimming_lanes')->default(false)->after('has_indoor_swimming_lanes');

            // Children's pool (otroški bazen)
            $table->boolean('has_indoor_children_pool')->default(false)->after('has_outdoor_swimming_lanes');
            $table->boolean('has_outdoor_children_pool')->default(false)->after('has_indoor_children_pool');

            // Diving board (skakalnica)
            $table->boolean('has_indoor_diving_board')->default(false)->after('has_outdoor_children_pool');
            $table->boolean('has_outdoor_diving_board')->default(false)->after('has_indoor_diving_board');

            // Water slides (tobogan) - number of slides
            $table->integer('indoor_water_slides_count')->nullable()->after('has_outdoor_diving_board');
            $table->integer('outdoor_water_slides_count')->nullable()->after('indoor_water_slides_count');

            // Thermal pool
            $table->boolean('has_thermal_pool')->default(false)->after('outdoor_water_slides_count');

            // Climbing wall (plezalna stena)
            $table->boolean('has_indoor_climbing_wall')->default(false)->after('has_thermal_pool');
            $table->boolean('has_outdoor_climbing_wall')->default(false)->after('has_indoor_climbing_wall');

            // Lazy river (počasna reka)
            $table->boolean('has_indoor_lazy_river')->default(false)->after('has_outdoor_climbing_wall');
            $table->boolean('has_outdoor_lazy_river')->default(false)->after('has_indoor_lazy_river');

            // Wave pool (valovi)
            $table->boolean('has_indoor_wave_pool')->default(false)->after('has_outdoor_lazy_river');
            $table->boolean('has_outdoor_wave_pool')->default(false)->after('has_indoor_wave_pool');

            // Whirlpool
            $table->boolean('has_indoor_whirlpool')->default(false)->after('has_outdoor_wave_pool');
            $table->boolean('has_outdoor_whirlpool')->default(false)->after('has_indoor_whirlpool');

            // Surfing (deskanje)
            $table->boolean('has_indoor_surfing')->default(false)->after('has_outdoor_whirlpool');
            $table->boolean('has_outdoor_surfing')->default(false)->after('has_indoor_surfing');

            // Sauna
            $table->boolean('has_sauna')->default(false)->after('has_outdoor_surfing');

            // Wellness
            $table->boolean('has_wellness')->default(false)->after('has_sauna');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beach', function (Blueprint $table) {
            $table->dropColumn([
                'has_swimming_pool',
                'has_indoor_swimming_lanes',
                'has_outdoor_swimming_lanes',
                'has_indoor_children_pool',
                'has_outdoor_children_pool',
                'has_indoor_diving_board',
                'has_outdoor_diving_board',
                'indoor_water_slides_count',
                'outdoor_water_slides_count',
                'has_thermal_pool',
                'has_indoor_climbing_wall',
                'has_outdoor_climbing_wall',
                'has_indoor_lazy_river',
                'has_outdoor_lazy_river',
                'has_indoor_wave_pool',
                'has_outdoor_wave_pool',
                'has_indoor_whirlpool',
                'has_outdoor_whirlpool',
                'has_indoor_surfing',
                'has_outdoor_surfing',
                'has_sauna',
                'has_wellness',
            ]);
        });
    }
};
