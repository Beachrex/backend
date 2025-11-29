<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds latitude and longitude fields to city, region, island, and country tables
     * to enable weather data functionality for these geographic entities.
     */
    public function up(): void
    {
        // Add coordinates to city table
        Schema::table('city', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('island_id')
                ->comment('City latitude for weather data');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude')
                ->comment('City longitude for weather data');
            
            // Add index for coordinate lookups
            $table->index(['latitude', 'longitude'], 'city_coordinates_idx');
        });

        // Add coordinates to region table
        Schema::table('region', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('country_code')
                ->comment('Region latitude for weather data');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude')
                ->comment('Region longitude for weather data');
            
            // Add index for coordinate lookups
            $table->index(['latitude', 'longitude'], 'region_coordinates_idx');
        });

        // Add coordinates to island table
        Schema::table('island', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('region_id')
                ->comment('Island latitude for weather data');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude')
                ->comment('Island longitude for weather data');
            
            // Add index for coordinate lookups
            $table->index(['latitude', 'longitude'], 'island_coordinates_idx');
        });

        // Add coordinates to countries table
        Schema::table('countries', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('continent_code')
                ->comment('Country latitude (capital or centroid) for weather data');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude')
                ->comment('Country longitude (capital or centroid) for weather data');
            
            // Add index for coordinate lookups
            $table->index(['latitude', 'longitude'], 'countries_coordinates_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove coordinates from city table
        Schema::table('city', function (Blueprint $table) {
            $table->dropIndex('city_coordinates_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });

        // Remove coordinates from region table
        Schema::table('region', function (Blueprint $table) {
            $table->dropIndex('region_coordinates_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });

        // Remove coordinates from island table
        Schema::table('island', function (Blueprint $table) {
            $table->dropIndex('island_coordinates_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });

        // Remove coordinates from countries table
        Schema::table('countries', function (Blueprint $table) {
            $table->dropIndex('countries_coordinates_idx');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};

