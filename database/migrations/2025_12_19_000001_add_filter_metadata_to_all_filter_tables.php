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
        // Tables that need filter metadata columns
        $tables = [
            'accommodation_around',
            'culinary_offer',
            'location_infrastructure',
            'natural_location_condition',
            'sporting_activities',
            'underwater_characteristic',
            'recommended_transport_type',
            'accessibility',
            'area_description',
            'parking_capacity',
            'beach_inclination',
            'swimming_depth_of_one_m',
            'occupancy_rate',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->boolean('show_filter')->default(true)->after('status_id')
                    ->comment('Whether this option should be shown in filters');
                $table->string('icon', 255)->nullable()->after('show_filter')
                    ->comment('Icon file path (SVG/PNG) for filter display');
                $table->unsignedSmallInteger('filter_order')->default(0)->after('icon')
                    ->comment('Order in which this option appears in filters (lower = first)');
                $table->string('input_type', 20)->default('checkbox')->after('filter_order')
                    ->comment('Input type for filter: checkbox, radio, select, single, multiple');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'accommodation_around',
            'culinary_offer',
            'location_infrastructure',
            'natural_location_condition',
            'sporting_activities',
            'underwater_characteristic',
            'recommended_transport_type',
            'accessibility',
            'area_description',
            'parking_capacity',
            'beach_inclination',
            'swimming_depth_of_one_m',
            'occupancy_rate',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['show_filter', 'icon', 'filter_order', 'input_type']);
            });
        }
    }
};

