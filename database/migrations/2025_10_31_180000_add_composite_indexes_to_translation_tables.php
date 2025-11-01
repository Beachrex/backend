<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add composite indexes on (foreign_id, locale) for all translation tables
     * to optimize the most common query pattern: WHERE foreign_id = ? AND locale = ?
     *
     * Add composite indexes on pivot tables for reverse lookups
     * to optimize queries like: WHERE feature_id = ? (finding all beaches for a feature)
     *
     * Add index on beach.user_id for user-specific beach queries
     *
     * Add indexes on status_id in pivot tables for filtering by active status
     *
     * Add indexes on foreign_id in translation tables for queries like "get all translations for beach X"
     *
     * Add indexes on code columns for lookups by code
     */
    public function up(): void
    {
        // ========================================
        // BEACH TABLE - Add user_id index
        // ========================================

        Schema::table('beach', function (Blueprint $table) {
            $table->index('user_id', 'beach_user_id_idx');
        });

        // ========================================
        // TRANSLATION TABLES - Composite Indexes
        // ========================================
        // Beach translation
        Schema::table('beach_translation', function (Blueprint $table) {
            $table->index('beach_id', 'beach_translation_beach_id_idx');
            $table->index(['beach_id', 'locale'], 'beach_translation_id_locale_idx');
        });

        // Waterbody translation
        Schema::table('waterbody_translation', function (Blueprint $table) {
            $table->index('waterbody_id', 'waterbody_translation_waterbody_id_idx');
            $table->index(['waterbody_id', 'locale'], 'waterbody_translation_id_locale_idx');
        });

        // Beach type translation
        Schema::table('beach_type_translation', function (Blueprint $table) {
            $table->index('beach_type_id', 'beach_type_translation_beach_type_id_idx');
            $table->index(['beach_type_id', 'locale'], 'beach_type_translation_id_locale_idx');
        });

        // Area description translation
        Schema::table('area_description_translation', function (Blueprint $table) {
            $table->index('area_description_id', 'area_description_translation_area_description_id_idx');
            $table->index(['area_description_id', 'locale'], 'area_description_translation_id_locale_idx');
        });

        // Accessibility translation
        Schema::table('accessibility_translation', function (Blueprint $table) {
            $table->index('accessibility_id', 'accessibility_translation_accessibility_id_idx');
            $table->index(['accessibility_id', 'locale'], 'accessibility_translation_id_locale_idx');
        });

        // Road to parking type translation
        Schema::table('road_to_parking_type_translation', function (Blueprint $table) {
            $table->index('road_to_parking_type_id', 'road_to_parking_type_trans_fk_idx');
            $table->index(['road_to_parking_type_id', 'locale'], 'road_to_parking_type_trans_fk_locale_idx');
        });

        // Parking capacity translation
        Schema::table('parking_capacity_translation', function (Blueprint $table) {
            $table->index('parking_capacity_id', 'parking_capacity_trans_fk_idx');
            $table->index(['parking_capacity_id', 'locale'], 'parking_capacity_trans_fk_locale_idx');
        });

        // Parking beach distance translation
        Schema::table('parking_beach_distance_translation', function (Blueprint $table) {
            $table->index('parking_beach_distance_id', 'parking_beach_distance_trans_fk_idx');
            $table->index(['parking_beach_distance_id', 'locale'], 'parking_beach_distance_trans_fk_locale_idx');
        });

        // Parking beach height translation
        Schema::table('parking_beach_height_translation', function (Blueprint $table) {
            $table->index('parking_beach_height_id', 'parking_beach_height_trans_fk_idx');
            $table->index(['parking_beach_height_id', 'locale'], 'parking_beach_height_trans_fk_locale_idx');
        });

        // Location length translation
        Schema::table('location_length_translation', function (Blueprint $table) {
            $table->index('location_length_id', 'location_length_translation_location_length_id_idx');
            $table->index(['location_length_id', 'locale'], 'location_length_translation_id_locale_idx');
        });

        // Bay length translation
        Schema::table('bay_length_translation', function (Blueprint $table) {
            $table->index('bay_length_id', 'bay_length_translation_bay_length_id_idx');
            $table->index(['bay_length_id', 'locale'], 'bay_length_translation_id_locale_idx');
        });

        // Beach inclination translation
        Schema::table('beach_inclination_translation', function (Blueprint $table) {
            $table->index('beach_inclination_id', 'beach_inclination_translation_beach_inclination_id_idx');
            $table->index(['beach_inclination_id', 'locale'], 'beach_inclination_translation_id_locale_idx');
        });

        // Swimming depth of one m translation
        Schema::table('swimming_depth_of_one_m_translation', function (Blueprint $table) {
            $table->index('swimming_depth_of_one_m_id', 'swimming_depth_of_one_m_trans_fk_idx');
            $table->index(['swimming_depth_of_one_m_id', 'locale'], 'swimming_depth_of_one_m_trans_fk_locale_idx');
        });

        // Occupancy rate translation
        Schema::table('occupancy_rate_translation', function (Blueprint $table) {
            $table->index('occupancy_rate_id', 'occupancy_rate_translation_occupancy_rate_id_idx');
            $table->index(['occupancy_rate_id', 'locale'], 'occupancy_rate_translation_id_locale_idx');
        });

        // Accommodation around translation
        Schema::table('accommodation_around_translation', function (Blueprint $table) {
            $table->index('accommodation_around_id', 'accommodation_around_trans_fk_idx');
            $table->index(['accommodation_around_id', 'locale'], 'accommodation_around_trans_fk_locale_idx');
        });

        // Culinary offer translation
        Schema::table('culinary_offer_translation', function (Blueprint $table) {
            $table->index('culinary_offer_id', 'culinary_offer_trans_fk_idx');
            $table->index(['culinary_offer_id', 'locale'], 'culinary_offer_trans_fk_locale_idx');
        });

        // Location infrastructure translation
        Schema::table('location_infrastructure_translation', function (Blueprint $table) {
            $table->index('location_infrastructure_id', 'location_infrastructure_trans_fk_idx');
            $table->index(['location_infrastructure_id', 'locale'], 'location_infrastructure_trans_fk_locale_idx');
        });

        // Natural location condition translation
        Schema::table('natural_location_condition_translation', function (Blueprint $table) {
            $table->index('natural_location_condition_id', 'natural_location_condition_trans_fk_idx');
            $table->index(['natural_location_condition_id', 'locale'], 'natural_location_condition_trans_fk_locale_idx');
        });

        // Recommended transport type translation
        Schema::table('recommended_transport_type_translation', function (Blueprint $table) {
            $table->index('recommended_transport_type_id', 'recommended_transport_type_trans_fk_idx');
            $table->index(['recommended_transport_type_id', 'locale'], 'recommended_transport_type_trans_fk_locale_idx');
        });

        // Sporting activities translation
        Schema::table('sporting_activities_translation', function (Blueprint $table) {
            $table->index('sporting_activities_id', 'sporting_activities_trans_fk_idx');
            $table->index(['sporting_activities_id', 'locale'], 'sporting_activities_trans_fk_locale_idx');
        });

        // Underwater characteristic translation
        Schema::table('underwater_characteristic_translation', function (Blueprint $table) {
            $table->index('underwater_characteristic_id', 'underwater_characteristic_trans_fk_idx');
            $table->index(['underwater_characteristic_id', 'locale'], 'underwater_characteristic_trans_fk_locale_idx');
        });

        // User type translation
        Schema::table('user_type_translation', function (Blueprint $table) {
            $table->index('user_type_id', 'user_type_translation_user_type_id_idx');
            $table->index(['user_type_id', 'locale'], 'user_type_translation_id_locale_idx');
        });

        // ========================================
        // PIVOT TABLES - Composite Indexes for Reverse Lookups
        // ========================================

        // Accommodation around beach - reverse lookup
        Schema::table('accommodation_around_beach', function (Blueprint $table) {
            $table->index(['accommodation_around_id', 'beach_id'], 'accommodation_around_beach_reverse_idx');
        });

        // Culinary offer beach - reverse lookup
        Schema::table('culinary_offer_beach', function (Blueprint $table) {
            $table->index(['culinary_offer_id', 'beach_id'], 'culinary_offer_beach_reverse_idx');
        });

        // Location infrastructure beach - reverse lookup
        Schema::table('location_infrastructure_beach', function (Blueprint $table) {
            $table->index(['location_infrastructure_id', 'beach_id'], 'location_infrastructure_beach_reverse_idx');
        });

        // Natural location condition beach - reverse lookup
        Schema::table('natural_location_condition_beach', function (Blueprint $table) {
            $table->index(['natural_location_condition_id', 'beach_id'], 'natural_location_condition_beach_reverse_idx');
        });

        // Recommended transport type beach - reverse lookup
        Schema::table('recommended_transport_type_beach', function (Blueprint $table) {
            $table->index(['recommended_transport_type_id', 'beach_id'], 'recommended_transport_type_beach_reverse_idx');
        });

        // Sporting activities beach - reverse lookup
        Schema::table('sporting_activities_beach', function (Blueprint $table) {
            $table->index(['sporting_activities_id', 'beach_id'], 'sporting_activities_beach_reverse_idx');
        });

        // Underwater characteristic beach - reverse lookup
        Schema::table('underwater_characteristic_beach', function (Blueprint $table) {
            $table->index(['underwater_characteristic_id', 'beach_id'], 'underwater_characteristic_beach_reverse_idx');
        });

        // User type beach - reverse lookup
        Schema::table('user_type_beach', function (Blueprint $table) {
            $table->index(['user_type_id', 'beach_id'], 'user_type_beach_reverse_idx');
        });

        // ========================================
        // PIVOT TABLES - Add status_id indexes
        // ========================================

        // Accommodation around beach - status_id index
        Schema::table('accommodation_around_beach', function (Blueprint $table) {
            $table->index('status_id', 'accommodation_around_beach_status_idx');
        });

        // Culinary offer beach - status_id index
        Schema::table('culinary_offer_beach', function (Blueprint $table) {
            $table->index('status_id', 'culinary_offer_beach_status_idx');
        });

        // Location infrastructure beach - status_id index
        Schema::table('location_infrastructure_beach', function (Blueprint $table) {
            $table->index('status_id', 'location_infrastructure_beach_status_idx');
        });

        // Natural location condition beach - status_id index
        Schema::table('natural_location_condition_beach', function (Blueprint $table) {
            $table->index('status_id', 'natural_location_condition_beach_status_idx');
        });

        // Recommended transport type beach - status_id index
        Schema::table('recommended_transport_type_beach', function (Blueprint $table) {
            $table->index('status_id', 'recommended_transport_type_beach_status_idx');
        });

        // Sporting activities beach - status_id index
        Schema::table('sporting_activities_beach', function (Blueprint $table) {
            $table->index('status_id', 'sporting_activities_beach_status_idx');
        });

        // Underwater characteristic beach - status_id index
        Schema::table('underwater_characteristic_beach', function (Blueprint $table) {
            $table->index('status_id', 'underwater_characteristic_beach_status_idx');
        });

        // User type beach - status_id index
        Schema::table('user_type_beach', function (Blueprint $table) {
            $table->index('status_id', 'user_type_beach_status_idx');
        });

        // ========================================
        // CODE COLUMNS - Add indexes for code lookups
        // ========================================

        // Continents - code index (already unique, but explicit index helps)
        Schema::table('continents', function (Blueprint $table) {
            $table->index('code', 'continents_code_idx');
        });

        // Countries - code index
        Schema::table('countries', function (Blueprint $table) {
            $table->index('code', 'countries_code_idx');
        });

        // Waterbody - code index
        Schema::table('waterbody', function (Blueprint $table) {
            $table->index('code', 'waterbody_code_idx');
        });

        // Beach type - code index
        Schema::table('beach_type', function (Blueprint $table) {
            $table->index('code', 'beach_type_code_idx');
        });

        // Area description - code index
        Schema::table('area_description', function (Blueprint $table) {
            $table->index('code', 'area_description_code_idx');
        });

        // Accessibility - code index
        Schema::table('accessibility', function (Blueprint $table) {
            $table->index('code', 'accessibility_code_idx');
        });

        // Road to parking type - code index
        Schema::table('road_to_parking_type', function (Blueprint $table) {
            $table->index('code', 'road_to_parking_type_code_idx');
        });

        // Parking capacity - code index
        Schema::table('parking_capacity', function (Blueprint $table) {
            $table->index('code', 'parking_capacity_code_idx');
        });

        // Parking beach distance - code index
        Schema::table('parking_beach_distance', function (Blueprint $table) {
            $table->index('code', 'parking_beach_distance_code_idx');
        });

        // Parking beach height - code index
        Schema::table('parking_beach_height', function (Blueprint $table) {
            $table->index('code', 'parking_beach_height_code_idx');
        });

        // Location length - code index
        Schema::table('location_length', function (Blueprint $table) {
            $table->index('code', 'location_length_code_idx');
        });

        // Bay length - code index
        Schema::table('bay_length', function (Blueprint $table) {
            $table->index('code', 'bay_length_code_idx');
        });

        // Beach inclination - code index
        Schema::table('beach_inclination', function (Blueprint $table) {
            $table->index('code', 'beach_inclination_code_idx');
        });

        // Swimming depth of one m - code index
        Schema::table('swimming_depth_of_one_m', function (Blueprint $table) {
            $table->index('code', 'swimming_depth_of_one_m_code_idx');
        });

        // Occupancy rate - code index
        Schema::table('occupancy_rate', function (Blueprint $table) {
            $table->index('code', 'occupancy_rate_code_idx');
        });

        // Accommodation around - code index
        Schema::table('accommodation_around', function (Blueprint $table) {
            $table->index('code', 'accommodation_around_code_idx');
        });

        // Culinary offer - code index
        Schema::table('culinary_offer', function (Blueprint $table) {
            $table->index('code', 'culinary_offer_code_idx');
        });

        // Location infrastructure - code index
        Schema::table('location_infrastructure', function (Blueprint $table) {
            $table->index('code', 'location_infrastructure_code_idx');
        });

        // Natural location condition - code index
        Schema::table('natural_location_condition', function (Blueprint $table) {
            $table->index('code', 'natural_location_condition_code_idx');
        });

        // Recommended transport type - code index
        Schema::table('recommended_transport_type', function (Blueprint $table) {
            $table->index('code', 'recommended_transport_type_code_idx');
        });

        // Sporting activities - code index
        Schema::table('sporting_activities', function (Blueprint $table) {
            $table->index('code', 'sporting_activities_code_idx');
        });

        // Underwater characteristic - code index
        Schema::table('underwater_characteristic', function (Blueprint $table) {
            $table->index('code', 'underwater_characteristic_code_idx');
        });

        // User type - code index
        Schema::table('user_type', function (Blueprint $table) {
            $table->index('code', 'user_type_code_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ========================================
        // BEACH TABLE - Drop user_id index
        // ========================================

        Schema::table('beach', function (Blueprint $table) {
            $table->dropIndex('beach_user_id_idx');
        });

        // ========================================
        // TRANSLATION TABLES - Drop Composite Indexes
        // ========================================

        // Beach translation
        Schema::table('beach_translation', function (Blueprint $table) {
            $table->dropIndex('beach_translation_id_locale_idx');
            $table->dropIndex('beach_translation_beach_id_idx');
        });

        // Waterbody translation
        Schema::table('waterbody_translation', function (Blueprint $table) {
            $table->dropIndex('waterbody_translation_id_locale_idx');
            $table->dropIndex('waterbody_translation_waterbody_id_idx');
        });

        // Beach type translation
        Schema::table('beach_type_translation', function (Blueprint $table) {
            $table->dropIndex('beach_type_translation_id_locale_idx');
            $table->dropIndex('beach_type_translation_beach_type_id_idx');
        });

        // Area description translation
        Schema::table('area_description_translation', function (Blueprint $table) {
            $table->dropIndex('area_description_translation_id_locale_idx');
            $table->dropIndex('area_description_translation_area_description_id_idx');
        });

        // Accessibility translation
        Schema::table('accessibility_translation', function (Blueprint $table) {
            $table->dropIndex('accessibility_translation_id_locale_idx');
            $table->dropIndex('accessibility_translation_accessibility_id_idx');
        });

        // Road to parking type translation
        Schema::table('road_to_parking_type_translation', function (Blueprint $table) {
            $table->dropIndex('road_to_parking_type_trans_fk_locale_idx');
            $table->dropIndex('road_to_parking_type_trans_fk_idx');
        });

        // Parking capacity translation
        Schema::table('parking_capacity_translation', function (Blueprint $table) {
            $table->dropIndex('parking_capacity_trans_fk_locale_idx');
            $table->dropIndex('parking_capacity_trans_fk_idx');
        });

        // Parking beach distance translation
        Schema::table('parking_beach_distance_translation', function (Blueprint $table) {
            $table->dropIndex('parking_beach_distance_trans_fk_locale_idx');
            $table->dropIndex('parking_beach_distance_trans_fk_idx');
        });

        // Parking beach height translation
        Schema::table('parking_beach_height_translation', function (Blueprint $table) {
            $table->dropIndex('parking_beach_height_trans_fk_locale_idx');
            $table->dropIndex('parking_beach_height_trans_fk_idx');
        });

        // Location length translation
        Schema::table('location_length_translation', function (Blueprint $table) {
            $table->dropIndex('location_length_translation_id_locale_idx');
            $table->dropIndex('location_length_translation_location_length_id_idx');
        });

        // Bay length translation
        Schema::table('bay_length_translation', function (Blueprint $table) {
            $table->dropIndex('bay_length_translation_id_locale_idx');
            $table->dropIndex('bay_length_translation_bay_length_id_idx');
        });

        // Beach inclination translation
        Schema::table('beach_inclination_translation', function (Blueprint $table) {
            $table->dropIndex('beach_inclination_translation_id_locale_idx');
            $table->dropIndex('beach_inclination_translation_beach_inclination_id_idx');
        });

        // Swimming depth of one m translation
        Schema::table('swimming_depth_of_one_m_translation', function (Blueprint $table) {
            $table->dropIndex('swimming_depth_of_one_m_trans_fk_locale_idx');
            $table->dropIndex('swimming_depth_of_one_m_trans_fk_idx');
        });

        // Occupancy rate translation
        Schema::table('occupancy_rate_translation', function (Blueprint $table) {
            $table->dropIndex('occupancy_rate_translation_id_locale_idx');
            $table->dropIndex('occupancy_rate_translation_occupancy_rate_id_idx');
        });

        // Accommodation around translation
        Schema::table('accommodation_around_translation', function (Blueprint $table) {
            $table->dropIndex('accommodation_around_trans_fk_locale_idx');
            $table->dropIndex('accommodation_around_trans_fk_idx');
        });

        // Culinary offer translation
        Schema::table('culinary_offer_translation', function (Blueprint $table) {
            $table->dropIndex('culinary_offer_trans_fk_locale_idx');
            $table->dropIndex('culinary_offer_trans_fk_idx');
        });

        // Location infrastructure translation
        Schema::table('location_infrastructure_translation', function (Blueprint $table) {
            $table->dropIndex('location_infrastructure_trans_fk_locale_idx');
            $table->dropIndex('location_infrastructure_trans_fk_idx');
        });

        // Natural location condition translation
        Schema::table('natural_location_condition_translation', function (Blueprint $table) {
            $table->dropIndex('natural_location_condition_trans_fk_locale_idx');
            $table->dropIndex('natural_location_condition_trans_fk_idx');
        });

        // Recommended transport type translation
        Schema::table('recommended_transport_type_translation', function (Blueprint $table) {
            $table->dropIndex('recommended_transport_type_trans_fk_locale_idx');
            $table->dropIndex('recommended_transport_type_trans_fk_idx');
        });

        // Sporting activities translation
        Schema::table('sporting_activities_translation', function (Blueprint $table) {
            $table->dropIndex('sporting_activities_trans_fk_locale_idx');
            $table->dropIndex('sporting_activities_trans_fk_idx');
        });

        // Underwater characteristic translation
        Schema::table('underwater_characteristic_translation', function (Blueprint $table) {
            $table->dropIndex('underwater_characteristic_trans_fk_locale_idx');
            $table->dropIndex('underwater_characteristic_trans_fk_idx');
        });

        // User type translation
        Schema::table('user_type_translation', function (Blueprint $table) {
            $table->dropIndex('user_type_translation_id_locale_idx');
            $table->dropIndex('user_type_translation_user_type_id_idx');
        });

        // ========================================
        // PIVOT TABLES - Drop Reverse Lookup Indexes
        // ========================================

        // Accommodation around beach
        Schema::table('accommodation_around_beach', function (Blueprint $table) {
            $table->dropIndex('accommodation_around_beach_reverse_idx');
        });

        // Culinary offer beach
        Schema::table('culinary_offer_beach', function (Blueprint $table) {
            $table->dropIndex('culinary_offer_beach_reverse_idx');
        });

        // Location infrastructure beach
        Schema::table('location_infrastructure_beach', function (Blueprint $table) {
            $table->dropIndex('location_infrastructure_beach_reverse_idx');
        });

        // Natural location condition beach
        Schema::table('natural_location_condition_beach', function (Blueprint $table) {
            $table->dropIndex('natural_location_condition_beach_reverse_idx');
        });

        // Recommended transport type beach
        Schema::table('recommended_transport_type_beach', function (Blueprint $table) {
            $table->dropIndex('recommended_transport_type_beach_reverse_idx');
        });

        // Sporting activities beach
        Schema::table('sporting_activities_beach', function (Blueprint $table) {
            $table->dropIndex('sporting_activities_beach_reverse_idx');
        });

        // Underwater characteristic beach
        Schema::table('underwater_characteristic_beach', function (Blueprint $table) {
            $table->dropIndex('underwater_characteristic_beach_reverse_idx');
        });

        // User type beach
        Schema::table('user_type_beach', function (Blueprint $table) {
            $table->dropIndex('user_type_beach_reverse_idx');
        });

        // ========================================
        // PIVOT TABLES - Drop status_id indexes
        // ========================================

        // Accommodation around beach
        Schema::table('accommodation_around_beach', function (Blueprint $table) {
            $table->dropIndex('accommodation_around_beach_status_idx');
        });

        // Culinary offer beach
        Schema::table('culinary_offer_beach', function (Blueprint $table) {
            $table->dropIndex('culinary_offer_beach_status_idx');
        });

        // Location infrastructure beach
        Schema::table('location_infrastructure_beach', function (Blueprint $table) {
            $table->dropIndex('location_infrastructure_beach_status_idx');
        });

        // Natural location condition beach
        Schema::table('natural_location_condition_beach', function (Blueprint $table) {
            $table->dropIndex('natural_location_condition_beach_status_idx');
        });

        // Recommended transport type beach
        Schema::table('recommended_transport_type_beach', function (Blueprint $table) {
            $table->dropIndex('recommended_transport_type_beach_status_idx');
        });

        // Sporting activities beach
        Schema::table('sporting_activities_beach', function (Blueprint $table) {
            $table->dropIndex('sporting_activities_beach_status_idx');
        });

        // Underwater characteristic beach
        Schema::table('underwater_characteristic_beach', function (Blueprint $table) {
            $table->dropIndex('underwater_characteristic_beach_status_idx');
        });

        // User type beach
        Schema::table('user_type_beach', function (Blueprint $table) {
            $table->dropIndex('user_type_beach_status_idx');
        });

        // ========================================
        // CODE COLUMNS - Drop indexes
        // ========================================

        // Continents
        Schema::table('continents', function (Blueprint $table) {
            $table->dropIndex('continents_code_idx');
        });

        // Countries
        Schema::table('countries', function (Blueprint $table) {
            $table->dropIndex('countries_code_idx');
        });

        // Waterbody
        Schema::table('waterbody', function (Blueprint $table) {
            $table->dropIndex('waterbody_code_idx');
        });

        // Beach type
        Schema::table('beach_type', function (Blueprint $table) {
            $table->dropIndex('beach_type_code_idx');
        });

        // Area description
        Schema::table('area_description', function (Blueprint $table) {
            $table->dropIndex('area_description_code_idx');
        });

        // Accessibility
        Schema::table('accessibility', function (Blueprint $table) {
            $table->dropIndex('accessibility_code_idx');
        });

        // Road to parking type
        Schema::table('road_to_parking_type', function (Blueprint $table) {
            $table->dropIndex('road_to_parking_type_code_idx');
        });

        // Parking capacity
        Schema::table('parking_capacity', function (Blueprint $table) {
            $table->dropIndex('parking_capacity_code_idx');
        });

        // Parking beach distance
        Schema::table('parking_beach_distance', function (Blueprint $table) {
            $table->dropIndex('parking_beach_distance_code_idx');
        });

        // Parking beach height
        Schema::table('parking_beach_height', function (Blueprint $table) {
            $table->dropIndex('parking_beach_height_code_idx');
        });

        // Location length
        Schema::table('location_length', function (Blueprint $table) {
            $table->dropIndex('location_length_code_idx');
        });

        // Bay length
        Schema::table('bay_length', function (Blueprint $table) {
            $table->dropIndex('bay_length_code_idx');
        });

        // Beach inclination
        Schema::table('beach_inclination', function (Blueprint $table) {
            $table->dropIndex('beach_inclination_code_idx');
        });

        // Swimming depth of one m
        Schema::table('swimming_depth_of_one_m', function (Blueprint $table) {
            $table->dropIndex('swimming_depth_of_one_m_code_idx');
        });

        // Occupancy rate
        Schema::table('occupancy_rate', function (Blueprint $table) {
            $table->dropIndex('occupancy_rate_code_idx');
        });

        // Accommodation around
        Schema::table('accommodation_around', function (Blueprint $table) {
            $table->dropIndex('accommodation_around_code_idx');
        });

        // Culinary offer
        Schema::table('culinary_offer', function (Blueprint $table) {
            $table->dropIndex('culinary_offer_code_idx');
        });

        // Location infrastructure
        Schema::table('location_infrastructure', function (Blueprint $table) {
            $table->dropIndex('location_infrastructure_code_idx');
        });

        // Natural location condition
        Schema::table('natural_location_condition', function (Blueprint $table) {
            $table->dropIndex('natural_location_condition_code_idx');
        });

        // Recommended transport type
        Schema::table('recommended_transport_type', function (Blueprint $table) {
            $table->dropIndex('recommended_transport_type_code_idx');
        });

        // Sporting activities
        Schema::table('sporting_activities', function (Blueprint $table) {
            $table->dropIndex('sporting_activities_code_idx');
        });

        // Underwater characteristic
        Schema::table('underwater_characteristic', function (Blueprint $table) {
            $table->dropIndex('underwater_characteristic_code_idx');
        });

        // User type
        Schema::table('user_type', function (Blueprint $table) {
            $table->dropIndex('user_type_code_idx');
        });
    }
};

