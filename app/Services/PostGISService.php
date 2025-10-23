<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Beach;

class PostGISService
{
    /**
     * Find beaches within a given radius from a point
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function findBeachesWithinRadius(float $latitude, float $longitude, float $radiusKm, int $limit = 50)
    {
        $results = DB::select("
            SELECT
                b.id,
                b.name,
                b.latitude,
                b.longitude,
                b.country_code,
                b.continent_code,
                ST_Distance(
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                    ST_Transform(b.location, 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
              AND ST_DWithin(
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                    ST_Transform(b.location, 3857),
                    ?
                  )
            ORDER BY distance_km
            LIMIT ?
        ", [$longitude, $latitude, $longitude, $latitude, $radiusKm * 1000, $limit]);

        return collect($results);
    }

    /**
     * Find the nearest beaches to a given point
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function findNearestBeaches(float $latitude, float $longitude, int $limit = 10)
    {
        $results = DB::select("
            SELECT
                b.id,
                b.name,
                b.latitude,
                b.longitude,
                b.country_code,
                b.continent_code,
                ST_Distance(
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                    ST_Transform(b.location, 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
            ORDER BY ST_Distance(
                ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                ST_Transform(b.location, 3857)
            )
            LIMIT ?
        ", [$longitude, $latitude, $longitude, $latitude, $limit]);

        return collect($results);
    }

    /**
     * Calculate distance between two points
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in kilometers
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $result = DB::selectOne("
            SELECT ST_Distance(
                ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857)
            ) / 1000.0 as distance_km
        ", [$lon1, $lat1, $lon2, $lat2]);

        return (float) $result->distance_km;
    }

    /**
     * Get beaches within a bounding box
     *
     * @param float $minLat
     * @param float $minLon
     * @param float $maxLat
     * @param float $maxLon
     * @return \Illuminate\Support\Collection
     */
    public function getBeachesInBoundingBox(float $minLat, float $minLon, float $maxLat, float $maxLon)
    {
        $results = DB::select("
            SELECT
                b.id,
                b.name,
                b.latitude,
                b.longitude,
                b.country_code,
                b.continent_code
            FROM beach b
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.latitude BETWEEN ? AND ?
              AND b.longitude BETWEEN ? AND ?
            ORDER BY b.name
        ", [$minLat, $maxLat, $minLon, $maxLon]);

        return collect($results);
    }

    /**
     * Get beach density by region (for heatmap data)
     *
     * @param float $gridSize Grid size in degrees (default 1.0 = ~111km)
     * @return \Illuminate\Support\Collection
     */
    public function getBeachDensityGrid(float $gridSize = 1.0)
    {
        $results = DB::select("
            SELECT
                FLOOR(b.latitude / ?) * ? as grid_lat,
                FLOOR(b.longitude / ?) * ? as grid_lon,
                COUNT(*) as beach_count
            FROM beach b
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
            GROUP BY FLOOR(b.latitude / ?), FLOOR(b.longitude / ?)
            HAVING COUNT(*) > 0
            ORDER BY beach_count DESC
        ", [$gridSize, $gridSize, $gridSize, $gridSize, $gridSize, $gridSize]);

        return collect($results);
    }

    /**
     * Find beaches along a route (useful for road trips)
     *
     * @param array $waypoints Array of [lat, lon] coordinates
     * @param float $corridorWidthKm Width of search corridor in km
     * @return \Illuminate\Support\Collection
     */
    public function findBeachesAlongRoute(array $waypoints, float $corridorWidthKm = 50.0)
    {
        if (count($waypoints) < 2) {
            return collect([]);
        }

        // Create a simple corridor search for now
        // In a more advanced implementation, you'd create a proper route buffer
        $minLat = min(array_column($waypoints, 0)) - ($corridorWidthKm / 111.0);
        $maxLat = max(array_column($waypoints, 0)) + ($corridorWidthKm / 111.0);
        $minLon = min(array_column($waypoints, 1)) - ($corridorWidthKm / 111.0);
        $maxLon = max(array_column($waypoints, 1)) + ($corridorWidthKm / 111.0);

        return $this->getBeachesInBoundingBox($minLat, $minLon, $maxLat, $maxLon);
    }

    /**
     * Get statistics about beach distribution
     *
     * @return array
     */
    public function getBeachStatistics(): array
    {
        $stats = DB::selectOne("
            SELECT
                COUNT(*) as total_beaches,
                COUNT(DISTINCT country_code) as countries_with_beaches,
                COUNT(DISTINCT continent_code) as continents_with_beaches,
                AVG(latitude) as avg_latitude,
                AVG(longitude) as avg_longitude,
                MIN(latitude) as min_latitude,
                MAX(latitude) as max_latitude,
                MIN(longitude) as min_longitude,
                MAX(longitude) as max_longitude
            FROM beach
            WHERE latitude IS NOT NULL
              AND longitude IS NOT NULL
              AND status_id = 1
        ");

        return [
            'total_beaches' => (int) $stats->total_beaches,
            'countries_with_beaches' => (int) $stats->countries_with_beaches,
            'continents_with_beaches' => (int) $stats->continents_with_beaches,
            'center_point' => [
                'latitude' => (float) $stats->avg_latitude,
                'longitude' => (float) $stats->avg_longitude
            ],
            'bounding_box' => [
                'min_latitude' => (float) $stats->min_latitude,
                'max_latitude' => (float) $stats->max_latitude,
                'min_longitude' => (float) $stats->min_longitude,
                'max_longitude' => (float) $stats->max_longitude
            ]
        ];
    }
}
