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
        // Translation tables that need filter_title column
        $tables = [
            'accommodation_around_translation',
            'culinary_offer_translation',
            'location_infrastructure_translation',
            'natural_location_condition_translation',
            'sporting_activities_translation',
            'underwater_characteristic_translation',
            'recommended_transport_type_translation',
            'accessibility_translation',
            'area_description_translation',
            'parking_capacity_translation',
            'beach_inclination_translation',
            'swimming_depth_of_one_m_translation',
            'occupancy_rate_translation',
            'city_translation', // Add to city as well for consistency
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('filter_title', 100)->nullable()->after('name')
                    ->comment('Localized title for filter section (e.g., "Accommodation", "Activities")');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'accommodation_around_translation',
            'culinary_offer_translation',
            'location_infrastructure_translation',
            'natural_location_condition_translation',
            'sporting_activities_translation',
            'underwater_characteristic_translation',
            'recommended_transport_type_translation',
            'accessibility_translation',
            'area_description_translation',
            'parking_capacity_translation',
            'beach_inclination_translation',
            'swimming_depth_of_one_m_translation',
            'occupancy_rate_translation',
            'city_translation',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('filter_title');
            });
        }
    }
};

