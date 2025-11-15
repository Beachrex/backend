<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Beach;

class PostGISService
{
    protected BeachFilterService $filterService;

    public function __construct(BeachFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * Find beaches within a given radius from a point
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @param int $limit
     * @param Request|null $request
     * @return \Illuminate\Support\Collection
     */
    public function getBeachesWithinRadius(float $latitude, float $longitude, float $radiusKm, int $limit = 50, ?Request $request = null)
    {
        $locale = app()->getLocale();

        // Get filter conditions if request is provided
        $filterData = $request ? $this->filterService->buildRawFilters($request) : ['sql' => '', 'bindings' => []];
        $filterSql = $filterData['sql'];
        $filterBindings = $filterData['bindings'];

        $results = DB::select("
            SELECT
                b.id,
                b.folder_name,
                b.latitude,
                b.longitude,
                b.country_code,
                b.continent_code,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.description, bt_en.description) as description,
                COALESCE(bt.slug, bt_en.slug) as slug,
                ct.name as country_name,
                m.file_path as main_image_path,
                m.disk as main_image_disk,
                ST_Distance(
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                    ST_Transform(b.location, 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN media m ON b.id = m.mediable_id
                AND m.mediable_type = 'App\\Models\\Beach'
                AND m.media_collection_id = 1
                AND m.is_primary IS TRUE
                AND m.status_id = 1
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
              AND ST_DWithin(
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                    ST_Transform(b.location, 3857),
                    ?
                  )
              {$filterSql}
            ORDER BY distance_km
            LIMIT ?
        ", array_merge(
            [$longitude, $latitude, $locale, $locale, $longitude, $latitude, $radiusKm * 1000],
            $filterBindings,
            [$limit]
        ));

        return $this->formatBeachResults($results);
    }

    /**
     * Find the nearest beaches to a given point
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getNearestBeaches(float $latitude, float $longitude, int $limit = 10)
    {
        $locale = app()->getLocale();

        $results = DB::select("
            SELECT
                b.id,
                b.folder_name,
                b.latitude,
                b.longitude,
                b.country_code,
                b.continent_code,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.description, bt_en.description) as description,
                COALESCE(bt.slug, bt_en.slug) as slug,
                ct.name as country_name,
                m.file_path as main_image_path,
                m.disk as main_image_disk,
                ST_Distance(
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                    ST_Transform(b.location, 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN media m ON b.id = m.mediable_id
                AND m.mediable_type = 'App\\Models\\Beach'
                AND m.media_collection_id = 1
                AND m.is_primary IS TRUE
                AND m.status_id = 1
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
            ORDER BY ST_Distance(
                ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                ST_Transform(b.location, 3857)
            )
            LIMIT ?
        ", [$longitude, $latitude, $locale, $locale, $longitude, $latitude, $limit]);

        return $this->formatBeachResults($results);
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
        $locale = app()->getLocale();

        $results = DB::select("
            SELECT
                b.id,
                b.folder_name,
                b.latitude,
                b.longitude,
                b.country_code,
                b.continent_code,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.description, bt_en.description) as description,
                COALESCE(bt.slug, bt_en.slug) as slug,
                ct.name as country_name,
                m.file_path as main_image_path,
                m.disk as main_image_disk
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN media m ON b.id = m.mediable_id
                AND m.mediable_type = 'App\\Models\\Beach'
                AND m.media_collection_id = 1
                AND m.is_primary IS TRUE
                AND m.status_id = 1
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.latitude BETWEEN ? AND ?
              AND b.longitude BETWEEN ? AND ?
            ORDER BY COALESCE(bt.name, bt_en.name)
        ", [$locale, $locale, $minLat, $maxLat, $minLon, $maxLon]);

        return $this->formatBeachResults($results);
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

    /**
     * Format beach results with proper image URLs
     *
     * @param array $results
     * @return \Illuminate\Support\Collection
     */
    private function formatBeachResults(array $results)
    {
        return collect($results)->map(function ($beach) {
            // Ensure main_image starts with a forward slash
            $mainImage = null;
            if ($beach->main_image_path) {
                $mainImage = '/' . ltrim($beach->main_image_path, '/');
            }

            return [
                'id' => $beach->id,
                'name' => $beach->name,
                'slug' => $beach->slug,
                'description' => $beach->description,
                'latitude' => $beach->latitude,
                'longitude' => $beach->longitude,
                'country_code' => $beach->country_code,
                'country_name' => $beach->country_name,
                'continent_code' => $beach->continent_code,
                'main_image' => $mainImage,
                'distance_km' => $beach->distance_km ?? null,
            ];
        });
    }
}
