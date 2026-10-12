<?php

namespace App\Services;

use App\Support\BeachGallery;
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
                b.video_url,
                b.webcam,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.description, bt_en.description) as description,
                COALESCE(bt.slug, bt_en.slug) as slug,
                ct.name as country_name,
                COALESCE(ct.slug, ct_en.slug) as country_slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(reg_t.slug, reg_t_en.slug) as region_slug,
                COALESCE(isl_t.slug, isl_t_en.slug) as island_slug,
                COALESCE(city_t.name, city_t_en.name) as city_name,
                COALESCE(city_t.slug, city_t_en.slug) as city_slug,
                m.file_path as main_image_path,
                m.disk as main_image_disk,
                tags.tags_json,
                ST_Distance(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    b.location::geography
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN continent_translation cont_t ON cont.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON cont.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN country_translation ct_en ON c.code = ct_en.country_code AND ct_en.locale = 'en'
            LEFT JOIN region reg ON b.region_id = reg.id
            LEFT JOIN region_translation reg_t ON reg.id = reg_t.region_id AND reg_t.locale = ?
            LEFT JOIN region_translation reg_t_en ON reg.id = reg_t_en.region_id AND reg_t_en.locale = 'en'
            LEFT JOIN island isl ON b.island_id = isl.id
            LEFT JOIN island_translation isl_t ON isl.id = isl_t.island_id AND isl_t.locale = ?
            LEFT JOIN island_translation isl_t_en ON isl.id = isl_t_en.island_id AND isl_t_en.locale = 'en'
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN city_translation city_t ON city.id = city_t.city_id AND city_t.locale = ?
            LEFT JOIN city_translation city_t_en ON city.id = city_t_en.city_id AND city_t_en.locale = 'en'
            LEFT JOIN waterbody wb ON b.waterbody_id = wb.id
            LEFT JOIN beach_type btype ON b.beach_type_id = btype.id
            LEFT JOIN accessibility acc ON b.accessibility_id = acc.id
            LEFT JOIN area_description ad ON b.area_description_id = ad.id
            LEFT JOIN parking_capacity pc ON b.parking_capacity_id = pc.id
            LEFT JOIN beach_inclination bi ON b.beach_inclination_id = bi.id
            LEFT JOIN swimming_depth_of_one_m sd ON b.swimming_depth_of_one_m_id = sd.id
            LEFT JOIN occupancy_rate occ ON b.occupancy_rate_id = occ.id
            LEFT JOIN LATERAL (
                SELECT file_path, disk FROM media
                WHERE mediable_id = b.id
                  AND mediable_type = 'App\\Models\\Beach'
                  AND media_collection_id = 1
                  AND is_primary IS TRUE
                  AND status_id = 1
                ORDER BY id
                LIMIT 1
            ) m ON TRUE
            LEFT JOIN LATERAL (
                SELECT json_agg(tag_name) AS tags_json FROM (
                    SELECT COALESCE(sat.name, sat_en.name) AS tag_name
                    FROM sporting_activities_beach sab
                    JOIN sporting_activities sa ON sab.sporting_activities_id = sa.id
                    LEFT JOIN sporting_activities_translation sat ON sa.id = sat.sporting_activities_id AND sat.locale = ?
                    LEFT JOIN sporting_activities_translation sat_en ON sa.id = sat_en.sporting_activities_id AND sat_en.locale = 'en'
                    WHERE sab.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(cot.name, cot_en.name) AS tag_name
                    FROM culinary_offer_beach cob
                    JOIN culinary_offer co ON cob.culinary_offer_id = co.id
                    LEFT JOIN culinary_offer_translation cot ON co.id = cot.culinary_offer_id AND cot.locale = ?
                    LEFT JOIN culinary_offer_translation cot_en ON co.id = cot_en.culinary_offer_id AND cot_en.locale = 'en'
                    WHERE cob.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(nlct.name, nlct_en.name) AS tag_name
                    FROM natural_location_condition_beach nlcb
                    JOIN natural_location_condition nlc ON nlcb.natural_location_condition_id = nlc.id
                    LEFT JOIN natural_location_condition_translation nlct ON nlc.id = nlct.natural_location_condition_id AND nlct.locale = ?
                    LEFT JOIN natural_location_condition_translation nlct_en ON nlc.id = nlct_en.natural_location_condition_id AND nlct_en.locale = 'en'
                    WHERE nlcb.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(orct.name, orct_en.name) AS tag_name
                    FROM occupancy_rate_translation orct
                    LEFT JOIN occupancy_rate_translation orct_en ON orct_en.occupancy_rate_id = b.occupancy_rate_id AND orct_en.locale = 'en'
                    WHERE orct.occupancy_rate_id = b.occupancy_rate_id AND orct.locale = ?
                      AND b.occupancy_rate_id IS NOT NULL
                    LIMIT 4
                ) t WHERE tag_name IS NOT NULL
            ) tags ON TRUE
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
              AND ST_DWithin(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    b.location::geography,
                    ?
                  )
              {$filterSql}
            ORDER BY distance_km
            LIMIT ?
        ", array_merge(
            [$longitude, $latitude, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $longitude, $latitude, $radiusKm * 1000],
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
                b.video_url,
                b.webcam,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.description, bt_en.description) as description,
                COALESCE(bt.slug, bt_en.slug) as slug,
                ct.name as country_name,
                m.file_path as main_image_path,
                m.disk as main_image_disk,
                tags.tags_json,
                ST_Distance(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    b.location::geography
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN LATERAL (
                SELECT file_path, disk FROM media
                WHERE mediable_id = b.id
                  AND mediable_type = 'App\\Models\\Beach'
                  AND media_collection_id = 1
                  AND is_primary IS TRUE
                  AND status_id = 1
                ORDER BY id
                LIMIT 1
            ) m ON TRUE
            LEFT JOIN LATERAL (
                SELECT json_agg(tag_name) AS tags_json FROM (
                    SELECT COALESCE(sat.name, sat_en.name) AS tag_name
                    FROM sporting_activities_beach sab
                    JOIN sporting_activities sa ON sab.sporting_activities_id = sa.id
                    LEFT JOIN sporting_activities_translation sat ON sa.id = sat.sporting_activities_id AND sat.locale = ?
                    LEFT JOIN sporting_activities_translation sat_en ON sa.id = sat_en.sporting_activities_id AND sat_en.locale = 'en'
                    WHERE sab.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(cot.name, cot_en.name) AS tag_name
                    FROM culinary_offer_beach cob
                    JOIN culinary_offer co ON cob.culinary_offer_id = co.id
                    LEFT JOIN culinary_offer_translation cot ON co.id = cot.culinary_offer_id AND cot.locale = ?
                    LEFT JOIN culinary_offer_translation cot_en ON co.id = cot_en.culinary_offer_id AND cot_en.locale = 'en'
                    WHERE cob.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(nlct.name, nlct_en.name) AS tag_name
                    FROM natural_location_condition_beach nlcb
                    JOIN natural_location_condition nlc ON nlcb.natural_location_condition_id = nlc.id
                    LEFT JOIN natural_location_condition_translation nlct ON nlc.id = nlct.natural_location_condition_id AND nlct.locale = ?
                    LEFT JOIN natural_location_condition_translation nlct_en ON nlc.id = nlct_en.natural_location_condition_id AND nlct_en.locale = 'en'
                    WHERE nlcb.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(orct.name, orct_en.name) AS tag_name
                    FROM occupancy_rate_translation orct
                    LEFT JOIN occupancy_rate_translation orct_en ON orct_en.occupancy_rate_id = b.occupancy_rate_id AND orct_en.locale = 'en'
                    WHERE orct.occupancy_rate_id = b.occupancy_rate_id AND orct.locale = ?
                      AND b.occupancy_rate_id IS NOT NULL
                    LIMIT 4
                ) t WHERE tag_name IS NOT NULL
            ) tags ON TRUE
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
            ORDER BY ST_Distance(
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                b.location::geography
            )
            LIMIT ?
        ", [$longitude, $latitude, $locale, $locale, $locale, $locale, $locale, $locale, $longitude, $latitude, $limit]);

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
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
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
     * @param \Illuminate\Http\Request|null $request
     * @return \Illuminate\Support\Collection
     */
    public function getBeachesInBoundingBox(float $minLat, float $minLon, float $maxLat, float $maxLon, ?Request $request = null)
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
                b.video_url,
                b.webcam,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.description, bt_en.description) as description,
                COALESCE(bt.slug, bt_en.slug) as slug,
                ct.name as country_name,
                COALESCE(ct.slug, ct_en.slug) as country_slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(reg_t.slug, reg_t_en.slug) as region_slug,
                COALESCE(isl_t.slug, isl_t_en.slug) as island_slug,
                COALESCE(city_t.name, city_t_en.name) as city_name,
                COALESCE(city_t.slug, city_t_en.slug) as city_slug,
                m.file_path as main_image_path,
                m.disk as main_image_disk,
                tags.tags_json
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN continent_translation cont_t ON cont.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON cont.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN country_translation ct_en ON c.code = ct_en.country_code AND ct_en.locale = 'en'
            LEFT JOIN region reg ON b.region_id = reg.id
            LEFT JOIN region_translation reg_t ON reg.id = reg_t.region_id AND reg_t.locale = ?
            LEFT JOIN region_translation reg_t_en ON reg.id = reg_t_en.region_id AND reg_t_en.locale = 'en'
            LEFT JOIN island isl ON b.island_id = isl.id
            LEFT JOIN island_translation isl_t ON isl.id = isl_t.island_id AND isl_t.locale = ?
            LEFT JOIN island_translation isl_t_en ON isl.id = isl_t_en.island_id AND isl_t_en.locale = 'en'
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN city_translation city_t ON city.id = city_t.city_id AND city_t.locale = ?
            LEFT JOIN city_translation city_t_en ON city.id = city_t_en.city_id AND city_t_en.locale = 'en'
            LEFT JOIN waterbody wb ON b.waterbody_id = wb.id
            LEFT JOIN beach_type btype ON b.beach_type_id = btype.id
            LEFT JOIN accessibility acc ON b.accessibility_id = acc.id
            LEFT JOIN area_description ad ON b.area_description_id = ad.id
            LEFT JOIN parking_capacity pc ON b.parking_capacity_id = pc.id
            LEFT JOIN beach_inclination bi ON b.beach_inclination_id = bi.id
            LEFT JOIN swimming_depth_of_one_m sd ON b.swimming_depth_of_one_m_id = sd.id
            LEFT JOIN occupancy_rate occ ON b.occupancy_rate_id = occ.id
            LEFT JOIN LATERAL (
                SELECT file_path, disk FROM media
                WHERE mediable_id = b.id
                  AND mediable_type = 'App\\Models\\Beach'
                  AND media_collection_id = 1
                  AND is_primary IS TRUE
                  AND status_id = 1
                ORDER BY id
                LIMIT 1
            ) m ON TRUE
            LEFT JOIN LATERAL (
                SELECT json_agg(tag_name) AS tags_json FROM (
                    SELECT COALESCE(sat.name, sat_en.name) AS tag_name
                    FROM sporting_activities_beach sab
                    JOIN sporting_activities sa ON sab.sporting_activities_id = sa.id
                    LEFT JOIN sporting_activities_translation sat ON sa.id = sat.sporting_activities_id AND sat.locale = ?
                    LEFT JOIN sporting_activities_translation sat_en ON sa.id = sat_en.sporting_activities_id AND sat_en.locale = 'en'
                    WHERE sab.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(cot.name, cot_en.name) AS tag_name
                    FROM culinary_offer_beach cob
                    JOIN culinary_offer co ON cob.culinary_offer_id = co.id
                    LEFT JOIN culinary_offer_translation cot ON co.id = cot.culinary_offer_id AND cot.locale = ?
                    LEFT JOIN culinary_offer_translation cot_en ON co.id = cot_en.culinary_offer_id AND cot_en.locale = 'en'
                    WHERE cob.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(nlct.name, nlct_en.name) AS tag_name
                    FROM natural_location_condition_beach nlcb
                    JOIN natural_location_condition nlc ON nlcb.natural_location_condition_id = nlc.id
                    LEFT JOIN natural_location_condition_translation nlct ON nlc.id = nlct.natural_location_condition_id AND nlct.locale = ?
                    LEFT JOIN natural_location_condition_translation nlct_en ON nlc.id = nlct_en.natural_location_condition_id AND nlct_en.locale = 'en'
                    WHERE nlcb.beach_id = b.id
                    UNION ALL
                    SELECT COALESCE(orct.name, orct_en.name) AS tag_name
                    FROM occupancy_rate_translation orct
                    LEFT JOIN occupancy_rate_translation orct_en ON orct_en.occupancy_rate_id = b.occupancy_rate_id AND orct_en.locale = 'en'
                    WHERE orct.occupancy_rate_id = b.occupancy_rate_id AND orct.locale = ?
                      AND b.occupancy_rate_id IS NOT NULL
                    LIMIT 4
                ) t WHERE tag_name IS NOT NULL
            ) tags ON TRUE
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.latitude BETWEEN ? AND ?
              AND b.longitude BETWEEN ? AND ?
              {$filterSql}
            ORDER BY COALESCE(bt.name, bt_en.name)
        ", array_merge(
            [$locale, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $locale, $minLat, $maxLat, $minLon, $maxLon],
            $filterBindings
        ));

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
     * Calculate centroid of beaches within radius
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @param \Illuminate\Http\Request|null $request
     * @return object|null
     */
    public function calculateCentroid(float $latitude, float $longitude, float $radiusKm, ?Request $request = null)
    {
        // Get filter conditions if request is provided
        $filterData = $request ? $this->filterService->buildRawFilters($request) : ['sql' => '', 'bindings' => []];
        $filterSql = $filterData['sql'];
        $filterBindings = $filterData['bindings'];

        $result = DB::selectOne("
            SELECT
                ST_Y(ST_Centroid(ST_Collect(b.location))) as center_lat,
                ST_X(ST_Centroid(ST_Collect(b.location))) as center_lng
            FROM beach b
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN region reg ON b.region_id = reg.id
            LEFT JOIN island isl ON b.island_id = isl.id
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN waterbody wb ON b.waterbody_id = wb.id
            LEFT JOIN beach_type btype ON b.beach_type_id = btype.id
            LEFT JOIN accessibility acc ON b.accessibility_id = acc.id
            LEFT JOIN area_description ad ON b.area_description_id = ad.id
            LEFT JOIN parking_capacity pc ON b.parking_capacity_id = pc.id
            LEFT JOIN beach_inclination bi ON b.beach_inclination_id = bi.id
            LEFT JOIN swimming_depth_of_one_m sd ON b.swimming_depth_of_one_m_id = sd.id
            LEFT JOIN occupancy_rate occ ON b.occupancy_rate_id = occ.id
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
              AND ST_DWithin(
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                    b.location::geography,
                    ?
                  )
              {$filterSql}
        ", array_merge(
            [$longitude, $latitude, $radiusKm * 1000],
            $filterBindings
        ));

        return $result;
    }

    /**
     * Calculate centroid of beaches within bounding box
     *
     * @param float $minLat
     * @param float $minLon
     * @param float $maxLat
     * @param float $maxLon
     * @param \Illuminate\Http\Request|null $request
     * @return object|null
     */
    public function calculateCentroidForBoundingBox(float $minLat, float $minLon, float $maxLat, float $maxLon, ?Request $request = null)
    {
        // Get filter conditions if request is provided
        $filterData = $request ? $this->filterService->buildRawFilters($request) : ['sql' => '', 'bindings' => []];
        $filterSql = $filterData['sql'];
        $filterBindings = $filterData['bindings'];

        $result = DB::selectOne("
            SELECT
                ST_Y(ST_Centroid(ST_Collect(b.location))) as center_lat,
                ST_X(ST_Centroid(ST_Collect(b.location))) as center_lng,
                MIN(ST_Y(b.location)) as min_lat,
                MAX(ST_Y(b.location)) as max_lat,
                MIN(ST_X(b.location)) as min_lng,
                MAX(ST_X(b.location)) as max_lng
            FROM beach b
            LEFT JOIN continents cont ON b.continent_code = cont.code
            LEFT JOIN countries c ON b.country_code = c.code
            LEFT JOIN region reg ON b.region_id = reg.id
            LEFT JOIN island isl ON b.island_id = isl.id
            LEFT JOIN city ON b.city_id = city.id
            LEFT JOIN waterbody wb ON b.waterbody_id = wb.id
            LEFT JOIN beach_type btype ON b.beach_type_id = btype.id
            LEFT JOIN accessibility acc ON b.accessibility_id = acc.id
            LEFT JOIN area_description ad ON b.area_description_id = ad.id
            LEFT JOIN parking_capacity pc ON b.parking_capacity_id = pc.id
            LEFT JOIN beach_inclination bi ON b.beach_inclination_id = bi.id
            LEFT JOIN swimming_depth_of_one_m sd ON b.swimming_depth_of_one_m_id = sd.id
            LEFT JOIN occupancy_rate occ ON b.occupancy_rate_id = occ.id
            WHERE b.latitude IS NOT NULL
              AND b.longitude IS NOT NULL
              AND b.status_id = 1
              AND b.location IS NOT NULL
              AND b.latitude BETWEEN ? AND ?
              AND b.longitude BETWEEN ? AND ?
              {$filterSql}
        ", array_merge(
            [$minLat, $maxLat, $minLon, $maxLon],
            $filterBindings
        ));

        return $result;
    }

    /**
     * Reverse geocode a point to find the nearest city and its geographic hierarchy.
     *
     * Finds the nearest active city (with active beaches) to the given coordinates
     * and returns its full geographic hierarchy with localized names and slugs.
     *
     * @param float $lat Latitude of the point
     * @param float $lng Longitude of the point
     * @return object|null Object with city_name, city_slug, region_slug, country_name, country_slug, country_code, continent_slug, or null if none found within 100km
     */
    public function reverseGeocodePoint(float $lat, float $lng): ?object
    {
        $locale = app()->getLocale();

        $result = DB::selectOne("
            SELECT
                COALESCE(city_t.name, city_t_en.name) as city_name,
                COALESCE(city_t.slug, city_t_en.slug) as city_slug,
                COALESCE(reg_t.slug, reg_t_en.slug) as region_slug,
                COALESCE(ct.name, ct_en.name) as country_name,
                COALESCE(ct.slug, ct_en.slug) as country_slug,
                c.code as country_code,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                ST_Distance(
                    ST_SetSRID(ST_MakePoint(city.longitude, city.latitude), 4326)::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) / 1000.0 as distance_km
            FROM city
            LEFT JOIN city_translation city_t ON city.id = city_t.city_id AND city_t.locale = ?
            LEFT JOIN city_translation city_t_en ON city.id = city_t_en.city_id AND city_t_en.locale = 'en'
            LEFT JOIN region reg ON city.region_id = reg.id
            LEFT JOIN region_translation reg_t ON reg.id = reg_t.region_id AND reg_t.locale = ?
            LEFT JOIN region_translation reg_t_en ON reg.id = reg_t_en.region_id AND reg_t_en.locale = 'en'
            LEFT JOIN countries c ON city.country_code = c.code
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN country_translation ct_en ON c.code = ct_en.country_code AND ct_en.locale = 'en'
            LEFT JOIN continents cont ON c.continent_code = cont.code
            LEFT JOIN continent_translation cont_t ON cont.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON cont.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            WHERE city.status_id = 1
              AND city.latitude IS NOT NULL
              AND city.longitude IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.city_id = city.id AND b.status_id = 1
              )
              AND ST_Distance(
                  ST_SetSRID(ST_MakePoint(city.longitude, city.latitude), 4326)::geography,
                  ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
              ) <= 100000
            ORDER BY ST_Distance(
                ST_SetSRID(ST_MakePoint(city.longitude, city.latitude), 4326)::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            )
            LIMIT 1
        ", [$lng, $lat, $locale, $locale, $locale, $locale, $lng, $lat, $lng, $lat]);

        return $result;
    }

    /**
     * Find nearby cities to a given point, ordered by distance
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getNearbyCities(float $latitude, float $longitude, float $radiusKm = 50, int $limit = 10)
    {
        $locale = app()->getLocale();

        $results = DB::select("
            SELECT
                c.id,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug,
                c.country_code,
                COALESCE(co_t.name, co_t_en.name) as country_name,
                COALESCE(co_t.slug, co_t_en.slug) as country_slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(reg_t.slug, reg_t_en.slug) as region_slug,
                COALESCE(isl_t.slug, isl_t_en.slug) as island_slug,
                c.latitude,
                c.longitude,
                (SELECT COUNT(*) FROM beach WHERE beach.city_id = c.id AND beach.status_id = 1) as beaches_count,
                c.main_image,
                ST_Distance(
                    ST_SetSRID(ST_MakePoint(c.longitude, c.latitude), 4326)::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) / 1000.0 as distance_km
            FROM city c
            LEFT JOIN city_translation ct ON c.id = ct.city_id AND ct.locale = ?
            LEFT JOIN city_translation ct_en ON c.id = ct_en.city_id AND ct_en.locale = 'en'
            LEFT JOIN countries co ON c.country_code = co.code
            LEFT JOIN country_translation co_t ON co.code = co_t.country_code AND co_t.locale = ?
            LEFT JOIN country_translation co_t_en ON co.code = co_t_en.country_code AND co_t_en.locale = 'en'
            LEFT JOIN continents cont ON co.continent_code = cont.code
            LEFT JOIN continent_translation cont_t ON cont.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON cont.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN region reg ON c.region_id = reg.id
            LEFT JOIN region_translation reg_t ON reg.id = reg_t.region_id AND reg_t.locale = ?
            LEFT JOIN region_translation reg_t_en ON reg.id = reg_t_en.region_id AND reg_t_en.locale = 'en'
            LEFT JOIN island isl ON c.island_id = isl.id
            LEFT JOIN island_translation isl_t ON isl.id = isl_t.island_id AND isl_t.locale = ?
            LEFT JOIN island_translation isl_t_en ON isl.id = isl_t_en.island_id AND isl_t_en.locale = 'en'
            WHERE c.status_id = 1
              AND c.latitude IS NOT NULL
              AND c.longitude IS NOT NULL
              AND EXISTS (SELECT 1 FROM beach WHERE beach.city_id = c.id AND beach.status_id = 1)
              AND ST_Distance(
                    ST_SetSRID(ST_MakePoint(c.longitude, c.latitude), 4326)::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                  ) / 1000.0 <= ?
            ORDER BY distance_km
            LIMIT ?
        ", [$longitude, $latitude, $locale, $locale, $locale, $locale, $locale, $longitude, $latitude, $radiusKm, $limit]);

        return collect($results)->map(function ($city) {
            return [
                'id' => $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'country_code' => $city->country_code,
                'country_name' => $city->country_name,
                'country_slug' => $city->country_slug ?? null,
                'continent_slug' => $city->continent_slug ?? null,
                'region_slug' => $city->region_slug ?? null,
                'island_slug' => $city->island_slug ?? null,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
                'beaches_count' => (int) $city->beaches_count,
                'main_image' => $city->main_image,
                'distance_km' => round((float) $city->distance_km, 2),
            ];
        });
    }

    /**
     * Format beach results with proper image URLs
     *
     * @param array $results
     * @return \Illuminate\Support\Collection
     */
    private function formatBeachResults(array $results)
    {
        // Get all beach IDs to load media
        $beachIds = collect($results)->pluck('id')->toArray();

        if (empty($beachIds)) {
            return collect([]);
        }

        // Load all media for these beaches (gallery, videos, 360 spheres)
        $allMedia = DB::select("
            SELECT
                m.mediable_id as beach_id,
                m.id,
                m.file_name,
                m.file_path as url,
                m.file_path as thumbnail_url,
                m.mime_type,
                m.width,
                m.height,
                m.duration,
                m.is_primary,
                m.order,
                mc.code as collection_code,
                b.folder_name,
                b.mobile_upload
            FROM media m
            INNER JOIN media_collections mc ON m.media_collection_id = mc.id
            INNER JOIN beach b ON m.mediable_id = b.id
            WHERE m.mediable_id IN (" . implode(',', $beachIds) . ")
              AND m.mediable_type = 'App\\Models\\Beach'
              AND mc.code IN ('beach_gallery', 'beach_videos', 'beach_360_sphere', '" . implode("', '", BeachGallery::FALLBACK_COLLECTIONS) . "')
              AND m.status_id = 1
            ORDER BY m.is_primary DESC, m.order, m.created_at
        ");

        // Group media by beach_id and collection
        $mediaByBeach = collect($allMedia)->groupBy('beach_id');

        $featuresByBeach = $this->loadCardFeatures($beachIds);

        return collect($results)->map(function ($beach) use ($mediaByBeach, $featuresByBeach) {
            // Return main_image path as-is (relative path without leading slash)
            $mainImage = $beach->main_image_path ?? null;

            // Get media for this beach
            $beachMedia = $mediaByBeach->get($beach->id, collect());

            // Group by collection
            $mediaByCollection = $beachMedia->groupBy('collection_code');

            // Format gallery - only essential fields, sorted by is_primary DESC, then order ASC
            $gallery = BeachGallery::pick($mediaByCollection)
                ->sortBy([
                    fn($a, $b) => $b->is_primary <=> $a->is_primary,  // Primary first (DESC)
                    fn($a, $b) => $a->order <=> $b->order,             // Then by order (ASC)
                ])
                ->map(function ($media) {
                    return [
                        'url' => $media->url,
                        'is_primary' => (bool) $media->is_primary,
                        'order' => $media->order,
                    ];
                })->values()->toArray();

                // No primary image (e.g. beaches uploaded from the mobile app, France): use the first gallery photo,
                // otherwise the web skips the card and region/city pages showed no beaches (owner 2026-10-12).
                $mainImage ??= $gallery[0]['url'] ?? null;

            // Format videos - only essential fields, sorted by is_primary DESC, then order ASC
            $videos = $mediaByCollection->get('beach_videos', collect())
                ->sortBy([
                    fn($a, $b) => $b->is_primary <=> $a->is_primary,  // Primary first (DESC)
                    fn($a, $b) => $a->order <=> $b->order,             // Then by order (ASC)
                ])
                ->map(function ($media) {
                    return [
                        'url' => $media->url,
                        'is_primary' => (bool) $media->is_primary,
                        'order' => $media->order,
                    ];
                })->values()->toArray();

            // Format 360 spheres - only tour_url needed
            $spheres360Collection = $mediaByCollection->get('beach_360_sphere', collect());
            $vtourUrl = null;
            if ($spheres360Collection->isNotEmpty() && isset($spheres360Collection->first()->folder_name)) {
                $sphere = $spheres360Collection->first();
                // Same rule as BeachDetailsResource: mobile uploads live under img/beaches_mobile/.
                $baseFolder = $sphere->mobile_upload ? 'beaches_mobile' : 'beaches';
                $vtourUrl = "img/{$baseFolder}/{$sphere->folder_name}/vtour/tour.html";
            }

            $spheres360 = [];
            if ($vtourUrl) {
                $spheres360 = $spheres360Collection->map(function ($media) use ($vtourUrl) {
                    return [
                        'tour_url' => $vtourUrl,
                    ];
                })->values()->toArray();
            }

            // Build media object - only include non-empty collections
            $media = [];
            if (!empty($gallery)) {
                $media['gallery'] = $gallery;
            }
            if (!empty($videos)) {
                $media['videos'] = $videos;
            }
            if (!empty($spheres360)) {
                $media['spheres_360'] = $spheres360;
            }

            // Add embeds under media
            $media['embeds'] = [
                'video_url' => $beach->video_url ?? null,
                'webcam' => $beach->webcam ?? null,
            ];

            return [
                'id' => $beach->id,
                'name' => $beach->name,
                'slug' => $beach->slug,
                'description' => $beach->description,
                'latitude' => $beach->latitude,
                'longitude' => $beach->longitude,
                'country_code' => $beach->country_code,
                'country_name' => $beach->country_name,
                'country_slug' => $beach->country_slug ?? null,
                'continent_code' => $beach->continent_code,
                'continent_slug' => $beach->continent_slug ?? null,
                'region_slug' => $beach->region_slug ?? null,
                'island_slug' => $beach->island_slug ?? null,
                'city_name' => $beach->city_name ?? null,
                'city_slug' => $beach->city_slug ?? null,
                'main_image' => $mainImage,
                'media' => $media,
                'tags' => isset($beach->tags_json) ? json_decode($beach->tags_json, true) : [],
                'features' => $featuresByBeach[$beach->id] ?? ['beach_type' => null, 'family' => false, 'adventurer' => false, 'youth' => false, 'parking' => false],
                'distance_km' => $beach->distance_km ?? null,
            ];
        });
    }

    /**
     * Card icons (QA #45): beach type, family friendly and parking, for every beach in one query.
     * Same rules as the beach page badges: family = user type `family`; parking = infrastructure
     * `parking` or a parking capacity other than none/private.
     *
     * @param  array<int>  $beachIds
     * @return array<int, array{beach_type: ?array{code: string, name: string}, family: bool, parking: bool}>
     */
    private function loadCardFeatures(array $beachIds): array
    {
        $ids = implode(',', array_map('intval', $beachIds));
        $locale = app()->getLocale();

        $rows = DB::select("
            SELECT
                b.id AS beach_id,
                bt.code AS beach_type_code,
                COALESCE(btl.name, bten.name, bt.code) AS beach_type_name,
                EXISTS (
                    SELECT 1 FROM user_type_beach utb
                    JOIN user_type ut ON ut.id = utb.user_type_id
                    WHERE utb.beach_id = b.id AND ut.code = 'family'
                ) AS family,
                EXISTS (
                    SELECT 1 FROM user_type_beach utb
                    JOIN user_type ut ON ut.id = utb.user_type_id
                    WHERE utb.beach_id = b.id AND ut.code = 'adventurer'
                ) AS adventurer,
                EXISTS (
                    SELECT 1 FROM user_type_beach utb
                    JOIN user_type ut ON ut.id = utb.user_type_id
                    WHERE utb.beach_id = b.id AND ut.code = 'youth'
                ) AS youth,
                (
                    EXISTS (
                        SELECT 1 FROM location_infrastructure_beach lib
                        JOIN location_infrastructure li ON li.id = lib.location_infrastructure_id
                        WHERE lib.beach_id = b.id AND li.code = 'parking'
                    )
                    OR (pc.code IS NOT NULL AND pc.code NOT IN ('0', 'private_parking_only'))
                ) AS parking
            FROM beach b
            LEFT JOIN beach_type bt ON bt.id = b.beach_type_id
            LEFT JOIN beach_type_translation btl ON btl.beach_type_id = bt.id AND btl.locale = ?
            LEFT JOIN beach_type_translation bten ON bten.beach_type_id = bt.id AND bten.locale = 'en'
            LEFT JOIN parking_capacity pc ON pc.id = b.parking_capacity_id
            WHERE b.id IN ($ids)
        ", [$locale]);

        $features = [];
        foreach ($rows as $row) {
            $features[$row->beach_id] = [
                'beach_type' => $row->beach_type_code
                    ? ['code' => $row->beach_type_code, 'name' => $row->beach_type_name]
                    : null,
                'family' => (bool) $row->family,
                'adventurer' => (bool) $row->adventurer,
                'youth' => (bool) $row->youth,
                'parking' => (bool) $row->parking,
            ];
        }

        return $features;
    }
}
