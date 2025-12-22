<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachDetailsResource;
use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Services\CacheService;
use App\Traits\CacheAware;
use App\Traits\ProvidesWeather;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @OA\Tag(
 *     name="Beach Details",
 *     description="API Endpoints for beach detail pages"
 * )
 */
class BeachDetailsController extends Controller
{
    use CacheAware, ProvidesWeather;
    /**
     * @OA\Get(
     *     path="/api/v1/beach-details/{identifier}",
     *     operationId="getBeachDetails",
     *     summary="Get comprehensive beach details by slug or ID",
     *     description="Returns all beach information including media galleries, characteristics, facilities, and related data. Accepts either beach ID (numeric) or slug (string) in any language.",
     *     tags={"Beach Details"},
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         required=true,
     *         description="Beach ID (numeric) or slug (string in any language)",
     *         @OA\Schema(
     *             oneOf={
     *                 @OA\Schema(type="integer", example=123),
     *                 @OA\Schema(type="string", example="paradise-beach")
     *             }
     *         )
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Beach details found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BeachDetailsSchema"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Beach not found"
     *     )
     * )
     */
    public function show(Request $request, $identifier)
    {
        $locale = app()->getLocale();
        $cacheKey = "beach_details:{$locale}:{$identifier}";

        // Cache the beach details for 8 hours
        // Weather data is fetched separately via /beach-details/{id}/weather with 1-hour cache
        $data = $this->cacheRemember($cacheKey, CacheService::BEACH_DETAILS_TTL, function () use ($request, $identifier, $locale) {
            // Determine if identifier is ID or slug
            $beach = $this->findBeachByIdentifier($identifier);

            if (!$beach) {
                return null;
            }

            // Get next and previous beaches using PostGIS
            $navigation = $this->getNavigationBeaches($beach, $locale);

            $resource = new BeachDetailsResource($beach);
            $resourceArray = $resource->toArray($request);

            // Add navigation to response
            $resourceArray['navigation'] = $navigation;

            return $resourceArray;
        });

        if (!$data) {
            return response()->json([
                'message' => 'Beach not found'
            ], 404);
        }

        // Increment view counter outside of cache (always increment)
        $beach = $this->findBeachByIdentifier($identifier);
        if ($beach) {
            $beach->increment('beach_views');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Find beach by ID or slug (in any language)
     */
    private function findBeachByIdentifier($identifier): ?Beach
    {
        // If identifier is numeric, search by ID
        if (is_numeric($identifier)) {
            return $this->loadBeachWithRelations((int) $identifier);
        }

        // Otherwise, search by slug in translations
        $translation = BeachTranslation::where('slug', $identifier)
            ->whereHas('beach', function ($query) {
                $query->where('status_id', 1); // Only active beaches
            })
            ->first();

        // If not found, check if there's a redirect for this slug
        if (!$translation) {
            try {
                // Check if beach_slug_redirects table exists (it may not exist in tests or before migration)
                if (Schema::hasTable('beach_slug_redirects')) {
                    $locale = app()->getLocale();
                    $redirect = DB::table('beach_slug_redirects')
                        ->where('old_slug', $identifier)
                        ->where('locale', $locale)
                        ->first();

                    if ($redirect) {
                        // Find beach by new slug
                        $translation = BeachTranslation::where('slug', $redirect->new_slug)
                            ->whereHas('beach', function ($query) {
                                $query->where('status_id', 1);
                            })
                            ->first();
                    }
                }
            } catch (\Exception $e) {
                // Silently fail if table doesn't exist - this is for backward compatibility
                // The beach simply won't be found, which is the expected behavior
            }
        }

        if (!$translation) {
            return null;
        }

        return $this->loadBeachWithRelations($translation->beach_id);
    }

    /**
     * Load beach with all necessary relationships
     */
    private function loadBeachWithRelations(int $beachId): ?Beach
    {
        return Beach::with([
            // Basic relationships
            'translations',
            'status',
            'user',

            // Location relationships with translations
            'continent.translations',
            'country.translations',
            'region.translations',
            'city.translations',
            'island.translations',
            'river.translations',
            'lake.translations',

            // Characteristic relationships with translations
            'waterbody.translations',
            'beachType.translations',
            'areaDescription.translations',
            'waterBodyType.translations',

            // Accessibility & Parking with translations
            'accessibility.translations',
            'roadToParkingType.translations',
            'parkingCapacity.translations',
            'parkingBeachDistance.translations',
            'parkingBeachHeight.translations',

            // Dimensions with translations
            'locationLength.translations',
            'bayLength.translations',
            'beachInclination.translations',
            'swimmingDepthOfOneM.translations',
            'occupancyRate.translations',

            // Many-to-many relationships with translations
            'accommodationArounds.translations',
            'culinaryOffers.translations',
            'locationInfrastructures.translations',
            'naturalLocationConditions.translations',
            'recommendedTransportTypes.translations',
            'sportingActivities.translations',
            'underwaterCharacteristics.translations',
            'userTypes.translations',
            'beachLocationTypes.translations',

            // Media collections - load all active media grouped by collection
            'media' => function ($query) {
                $query->where('status_id', 1)
                      ->with(['mediaCollection', 'mediaType'])
                      ->orderBy('is_primary', 'desc')
                      ->orderBy('order')
                      ->orderBy('created_at');
            }
        ])
        ->where('status_id', 1) // Only active beaches
        ->find($beachId);
    }

    /**
     * Get next and previous beaches based on region and ID order
     * Previous = highest ID < current ID in same region
     * Next = lowest ID > current ID in same region
     * First beach in region: previous = null
     * Last beach in region: next = null
     */
    private function getNavigationBeaches(Beach $currentBeach, string $locale): array
    {
        // If beach doesn't have a region, return null navigation
        if (!$currentBeach->region_id) {
            return [
                'previous' => null,
                'next' => null,
            ];
        }

        // Get previous beach: highest ID < current ID in same region
        $previousBeach = DB::select("
            SELECT
                b.id,
                b.folder_name,
                b.mobile_upload,
                b.latitude,
                b.longitude,
                b.country_code,
                b.region_id,
                b.island_id,
                b.city_id,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.slug, bt_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug,
                COALESCE(region_t.slug, region_t_en.slug) as region_slug,
                COALESCE(island_t.slug, island_t_en.slug) as island_slug,
                COALESCE(city_t.slug, city_t_en.slug) as city_slug,
                COALESCE(
                    (SELECT m.file_name
                     FROM media m
                     INNER JOIN media_types mt ON m.media_type_id = mt.id
                     INNER JOIN media_collections mc ON m.media_collection_id = mc.id
                     WHERE m.mediable_id = b.id
                       AND m.mediable_type = 'App\\Models\\Beach'
                       AND mt.code = 'image'
                       AND mc.code = 'beach_gallery'
                       AND m.status_id = 1
                       AND m.is_primary = TRUE
                     LIMIT 1),
                    (SELECT m.file_name
                     FROM media m
                     INNER JOIN media_types mt ON m.media_type_id = mt.id
                     INNER JOIN media_collections mc ON m.media_collection_id = mc.id
                     WHERE m.mediable_id = b.id
                       AND m.mediable_type = 'App\\Models\\Beach'
                       AND mt.code = 'image'
                       AND mc.code = 'beach_gallery'
                       AND m.status_id = 1
                     ORDER BY m.order, m.created_at
                     LIMIT 1)
                ) as main_image,
                ST_Distance(
                    ST_Transform(b.location, 3857),
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN countries ON b.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON b.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON b.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            LEFT JOIN region_translation region_t ON b.region_id = region_t.region_id AND region_t.locale = ?
            LEFT JOIN region_translation region_t_en ON b.region_id = region_t_en.region_id AND region_t_en.locale = 'en'
            LEFT JOIN island_translation island_t ON b.island_id = island_t.island_id AND island_t.locale = ?
            LEFT JOIN island_translation island_t_en ON b.island_id = island_t_en.island_id AND island_t_en.locale = 'en'
            LEFT JOIN city_translation city_t ON b.city_id = city_t.city_id AND city_t.locale = ?
            LEFT JOIN city_translation city_t_en ON b.city_id = city_t_en.city_id AND city_t_en.locale = 'en'
            WHERE b.region_id = ?
              AND b.id < ?
              AND b.status_id = 1
            ORDER BY b.id DESC
            LIMIT 1
        ", [
            $currentBeach->longitude,
            $currentBeach->latitude,
            $locale,
            $locale,
            $locale,
            $locale,
            $locale,
            $locale,
            $currentBeach->region_id,
            $currentBeach->id,
        ]);

        // Get next beach: lowest ID > current ID in same region
        $nextBeach = DB::select("
            SELECT
                b.id,
                b.folder_name,
                b.mobile_upload,
                b.latitude,
                b.longitude,
                b.country_code,
                b.region_id,
                b.island_id,
                b.city_id,
                COALESCE(bt.name, bt_en.name) as name,
                COALESCE(bt.slug, bt_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug,
                COALESCE(region_t.slug, region_t_en.slug) as region_slug,
                COALESCE(island_t.slug, island_t_en.slug) as island_slug,
                COALESCE(city_t.slug, city_t_en.slug) as city_slug,
                COALESCE(
                    (SELECT m.file_name
                     FROM media m
                     INNER JOIN media_types mt ON m.media_type_id = mt.id
                     INNER JOIN media_collections mc ON m.media_collection_id = mc.id
                     WHERE m.mediable_id = b.id
                       AND m.mediable_type = 'App\\Models\\Beach'
                       AND mt.code = 'image'
                       AND mc.code = 'beach_gallery'
                       AND m.status_id = 1
                       AND m.is_primary = TRUE
                     LIMIT 1),
                    (SELECT m.file_name
                     FROM media m
                     INNER JOIN media_types mt ON m.media_type_id = mt.id
                     INNER JOIN media_collections mc ON m.media_collection_id = mc.id
                     WHERE m.mediable_id = b.id
                       AND m.mediable_type = 'App\\Models\\Beach'
                       AND mt.code = 'image'
                       AND mc.code = 'beach_gallery'
                       AND m.status_id = 1
                     ORDER BY m.order, m.created_at
                     LIMIT 1)
                ) as main_image,
                ST_Distance(
                    ST_Transform(b.location, 3857),
                    ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857)
                ) / 1000.0 as distance_km
            FROM beach b
            LEFT JOIN beach_translation bt ON b.id = bt.beach_id AND bt.locale = ?
            LEFT JOIN beach_translation bt_en ON b.id = bt_en.beach_id AND bt_en.locale = 'en'
            LEFT JOIN countries ON b.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON b.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON b.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            LEFT JOIN region_translation region_t ON b.region_id = region_t.region_id AND region_t.locale = ?
            LEFT JOIN region_translation region_t_en ON b.region_id = region_t_en.region_id AND region_t_en.locale = 'en'
            LEFT JOIN island_translation island_t ON b.island_id = island_t.island_id AND island_t.locale = ?
            LEFT JOIN island_translation island_t_en ON b.island_id = island_t_en.island_id AND island_t_en.locale = 'en'
            LEFT JOIN city_translation city_t ON b.city_id = city_t.city_id AND city_t.locale = ?
            LEFT JOIN city_translation city_t_en ON b.city_id = city_t_en.city_id AND city_t_en.locale = 'en'
            WHERE b.region_id = ?
              AND b.id > ?
              AND b.status_id = 1
            ORDER BY b.id ASC
            LIMIT 1
        ", [
            $currentBeach->longitude,
            $currentBeach->latitude,
            $locale,
            $locale,
            $locale,
            $locale,
            $locale,
            $locale,
            $currentBeach->region_id,
            $currentBeach->id,
        ]);

        // Format the results
        $previous = isset($previousBeach[0]) ? [
            'id' => $previousBeach[0]->id,
            'name' => $previousBeach[0]->name,
            'slug' => $previousBeach[0]->slug,
            'latitude' => $previousBeach[0]->latitude,
            'longitude' => $previousBeach[0]->longitude,
            'distance_km' => round($previousBeach[0]->distance_km, 2),
            'main_image' => $this->formatMainImage($previousBeach[0]),
            'location' => [
                'continent_slug' => $previousBeach[0]->continent_slug,
                'country_slug' => $previousBeach[0]->country_slug,
                'region_slug' => $previousBeach[0]->region_slug,
                'island_slug' => $previousBeach[0]->island_slug,
                'city_slug' => $previousBeach[0]->city_slug,
            ],
        ] : null;

        $next = isset($nextBeach[0]) ? [
            'id' => $nextBeach[0]->id,
            'name' => $nextBeach[0]->name,
            'slug' => $nextBeach[0]->slug,
            'latitude' => $nextBeach[0]->latitude,
            'longitude' => $nextBeach[0]->longitude,
            'distance_km' => round($nextBeach[0]->distance_km, 2),
            'main_image' => $this->formatMainImage($nextBeach[0]),
            'location' => [
                'continent_slug' => $nextBeach[0]->continent_slug,
                'country_slug' => $nextBeach[0]->country_slug,
                'region_slug' => $nextBeach[0]->region_slug,
                'island_slug' => $nextBeach[0]->island_slug,
                'city_slug' => $nextBeach[0]->city_slug,
            ],
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }

    /**
     * Format main image path for navigation beaches
     */
    private function formatMainImage($beach): ?string
    {
        if (!$beach->main_image || !$beach->folder_name) {
            return null;
        }

        // Determine the path based on mobile_upload flag
        $path = $beach->mobile_upload ? 'img/beaches_mobile/' : 'img/beaches/';

        return $path . $beach->folder_name . '/' . $beach->main_image;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beach-details/{identifier}/weather",
     *     operationId="getBeachWeather",
     *     summary="Get current weather for a beach",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific beach. Weather data is cached for 1 hour. Accepts either beach ID (numeric) or slug (string) in any language.",
     *     tags={"Beach Details"},
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         required=true,
     *         description="Beach ID (numeric) or slug (string in any language)",
     *         @OA\Schema(
     *             oneOf={
     *                 @OA\Schema(type="integer", example=123),
     *                 @OA\Schema(type="string", example="paradise-beach")
     *             }
     *         )
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Weather data retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/BeachWeatherResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Beach not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Beach not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Beach has no coordinates",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Beach has no coordinates for weather data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Weather service unavailable",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Weather data temporarily unavailable")
     *         )
     *     )
     * )
     */
    public function weather($identifier): JsonResponse
    {
        // Find beach by ID or slug
        $beach = $this->findBeachByIdentifier($identifier);

        if (!$beach) {
            return response()->json([
                'success' => false,
                'message' => 'Beach not found'
            ], 404);
        }

        // Get beach slug in current locale
        $slug = $this->getBeachSlug($beach);

        // Use ProvidesWeather trait to build response
        return $this->buildWeatherResponse($beach, 'beach', $slug);
    }

    /**
     * Get beach slug in current locale
     */
    private function getBeachSlug(Beach $beach): ?string
    {
        $locale = app()->getLocale();

        $translation = $beach->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $beach->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }
}

