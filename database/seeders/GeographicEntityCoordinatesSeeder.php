<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographicEntityCoordinatesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     *
     * Populates latitude and longitude for cities, regions, islands, and countries
     * by calculating the centroid (average coordinates) of all beaches within each entity.
     */
    public function run(): void
    {
        $this->command->info('Starting to populate geographic entity coordinates...');

        // Populate city coordinates from beaches
        $this->populateCityCoordinates();

        // Populate island coordinates from beaches
        $this->populateIslandCoordinates();

        // Populate region coordinates from beaches
        $this->populateRegionCoordinates();

        // Populate country coordinates from beaches
        $this->populateCountryCoordinates();

        $this->command->info('Geographic entity coordinates populated successfully!');
    }

    /**
     * Populate city coordinates by calculating centroid of beaches in each city
     */
    private function populateCityCoordinates(): void
    {
        $this->command->info('Populating city coordinates...');

        // Calculate average coordinates for each city from its beaches
        $cityCoordinates = DB::table('beach')
            ->select(
                'city_id',
                DB::raw('AVG(latitude) as avg_latitude'),
                DB::raw('AVG(longitude) as avg_longitude'),
                DB::raw('COUNT(*) as beach_count')
            )
            ->whereNotNull('city_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status_id', 1) // Only active beaches
            ->groupBy('city_id')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($cityCoordinates as $coords) {
            $newLat = round($coords->avg_latitude, 7);
            $newLon = round($coords->avg_longitude, 7);

            // Check if coordinates already exist and are the same
            $city = DB::table('city')
                ->where('id', $coords->city_id)
                ->first(['latitude', 'longitude']);

            if ($city && $city->latitude == $newLat && $city->longitude == $newLon) {
                $skipped++;
                continue;
            }

            DB::table('city')
                ->where('id', $coords->city_id)
                ->update([
                    'latitude' => $newLat,
                    'longitude' => $newLon,
                ]);
            $updated++;
        }

        $this->command->info("Updated {$updated} cities with coordinates" . ($skipped > 0 ? " (skipped {$skipped} already up-to-date)" : ""));
    }

    /**
     * Populate island coordinates by calculating centroid of beaches on each island
     */
    private function populateIslandCoordinates(): void
    {
        $this->command->info('Populating island coordinates...');

        // Calculate average coordinates for each island from its beaches
        $islandCoordinates = DB::table('beach')
            ->select(
                'island_id',
                DB::raw('AVG(latitude) as avg_latitude'),
                DB::raw('AVG(longitude) as avg_longitude'),
                DB::raw('COUNT(*) as beach_count')
            )
            ->whereNotNull('island_id')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status_id', 1) // Only active beaches
            ->groupBy('island_id')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($islandCoordinates as $coords) {
            $newLat = round($coords->avg_latitude, 7);
            $newLon = round($coords->avg_longitude, 7);

            // Check if coordinates already exist and are the same
            $island = DB::table('island')
                ->where('id', $coords->island_id)
                ->first(['latitude', 'longitude']);

            if ($island && $island->latitude == $newLat && $island->longitude == $newLon) {
                $skipped++;
                continue;
            }

            DB::table('island')
                ->where('id', $coords->island_id)
                ->update([
                    'latitude' => $newLat,
                    'longitude' => $newLon,
                ]);
            $updated++;
        }

        $this->command->info("Updated {$updated} islands with coordinates" . ($skipped > 0 ? " (skipped {$skipped} already up-to-date)" : ""));
    }

    /**
     * Populate region coordinates by calculating centroid of beaches in each region
     */
    private function populateRegionCoordinates(): void
    {
        $this->command->info('Populating region coordinates...');

        // Calculate average coordinates for each region from its beaches
        // We need to join through city table to get region_id
        $regionCoordinates = DB::table('beach')
            ->join('city', 'beach.city_id', '=', 'city.id')
            ->select(
                'city.region_id',
                DB::raw('AVG(beach.latitude) as avg_latitude'),
                DB::raw('AVG(beach.longitude) as avg_longitude'),
                DB::raw('COUNT(*) as beach_count')
            )
            ->whereNotNull('city.region_id')
            ->whereNotNull('beach.latitude')
            ->whereNotNull('beach.longitude')
            ->where('beach.status_id', 1) // Only active beaches
            ->groupBy('city.region_id')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($regionCoordinates as $coords) {
            $newLat = round($coords->avg_latitude, 7);
            $newLon = round($coords->avg_longitude, 7);

            // Check if coordinates already exist and are the same
            $region = DB::table('region')
                ->where('id', $coords->region_id)
                ->first(['latitude', 'longitude']);

            if ($region && $region->latitude == $newLat && $region->longitude == $newLon) {
                $skipped++;
                continue;
            }

            DB::table('region')
                ->where('id', $coords->region_id)
                ->update([
                    'latitude' => $newLat,
                    'longitude' => $newLon,
                ]);
            $updated++;
        }

        $this->command->info("Updated {$updated} regions with coordinates" . ($skipped > 0 ? " (skipped {$skipped} already up-to-date)" : ""));
    }

    /**
     * Populate country coordinates by calculating centroid of beaches in each country
     */
    private function populateCountryCoordinates(): void
    {
        $this->command->info('Populating country coordinates...');

        // Calculate average coordinates for each country from its beaches
        $countryCoordinates = DB::table('beach')
            ->select(
                'country_code',
                DB::raw('AVG(latitude) as avg_latitude'),
                DB::raw('AVG(longitude) as avg_longitude'),
                DB::raw('COUNT(*) as beach_count')
            )
            ->whereNotNull('country_code')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('status_id', 1) // Only active beaches
            ->groupBy('country_code')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($countryCoordinates as $coords) {
            $newLat = round($coords->avg_latitude, 7);
            $newLon = round($coords->avg_longitude, 7);

            // Check if coordinates already exist and are the same
            $country = DB::table('countries')
                ->where('code', $coords->country_code)
                ->first(['latitude', 'longitude']);

            if ($country && $country->latitude == $newLat && $country->longitude == $newLon) {
                $skipped++;
                continue;
            }

            DB::table('countries')
                ->where('code', $coords->country_code)
                ->update([
                    'latitude' => $newLat,
                    'longitude' => $newLon,
                ]);
            $updated++;
        }

        $this->command->info("Updated {$updated} countries with coordinates" . ($skipped > 0 ? " (skipped {$skipped} already up-to-date)" : ""));
    }
}

