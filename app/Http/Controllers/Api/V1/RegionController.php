<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\CacheService;
use App\Support\PublishedBeachCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\CacheAware;
use App\Traits\ProvidesWeather;

/**
 * @OA\Tag(
 *     name="Regions",
 *     description="API Endpoints for regions"
 * )
 */
class RegionController extends Controller
{
    use CacheAware, ProvidesWeather;

    /**
     * @OA\Get(
     *     path="/api/v1/regions",
     *     operationId="getRegions",
     *     tags={"Regions"},
     *     summary="Get all regions that have beaches",
     *     description="Returns a list of regions that have at least one beach. Can be filtered by country. Returns minimal data: id, code, name, slug, country_code, beaches_count, and country object (without continent, badges, articles).",
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Filter by country code (e.g., SI, HR, IT)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Region")
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Regions retrieved successfully"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $countryFilter = $request->get('country_code');

            // Whole response cached per locale and filter (ApiResponseCache: JSON, not models; cleared on content changes).
            return \App\Support\ApiResponseCache::json('regions.index', ['country_code' => $countryFilter], \App\Support\ApiResponseCache::TTL_REFERENCE, function () use ($countryFilter) {
                $query = Region::with(['country.continent.translations', 'country.translations', 'translations'])
                    ->withBeaches()
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }]);

                // Filter by country if provided
                if ($countryFilter) {
                    $query->byCountry($countryFilter);
                }

                $regions = $query->get([
                    'id',
                    'code',
                    'country_code',
                    'main_image',
                    'status_id'
                ]);

                return [
                    'success' => true,
                    'data' => RegionResource::collection($regions),
                    'message' => 'Regions retrieved successfully'
                ];
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving regions',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/regions/{slug}",
     *     operationId="getRegion",
     *     tags={"Regions"},
     *     summary="Get a specific region",
     *     description="Returns a specific region by slug with full details including country, badges, and articles. Can be filtered by country code.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Region slug (e.g., istria, dalmatia)",
     *         required=true,
     *         @OA\Schema(type="string", example="istria")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Filter by country code (e.g., SI, HR, IT)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 allOf={
     *                     @OA\Schema(ref="#/components/schemas/Region"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="navigation",
     *                             type="object",
     *                             description="Navigation to previous and next regions with beaches in the same country",
     *                             @OA\Property(
     *                                 property="previous",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Istria"),
     *                                 @OA\Property(property="slug", type="string", example="istria"),
     *                                 @OA\Property(property="latitude", type="string", example="45.1"),
     *                                 @OA\Property(property="longitude", type="string", example="13.9"),
     *                                 @OA\Property(property="main_image", type="string", nullable=true, example="img/regions/istria.jpg"),
     *                                 @OA\Property(
     *                                     property="location",
     *                                     type="object",
     *                                     @OA\Property(property="continent_slug", type="string", example="europe"),
     *                                     @OA\Property(property="country_slug", type="string", example="croatia")
     *                                 )
     *                             ),
     *                             @OA\Property(
     *                                 property="next",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=3),
     *                                 @OA\Property(property="name", type="string", example="Dalmatia"),
     *                                 @OA\Property(property="slug", type="string", example="dalmatia"),
     *                                 @OA\Property(property="latitude", type="string", example="43.5"),
     *                                 @OA\Property(property="longitude", type="string", example="16.4"),
     *                                 @OA\Property(property="main_image", type="string", nullable=true, example="img/regions/dalmatia.jpg"),
     *                                 @OA\Property(
     *                                     property="location",
     *                                     type="object",
     *                                     @OA\Property(property="continent_slug", type="string", example="europe"),
     *                                     @OA\Property(property="country_slug", type="string", example="croatia")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 }
     *             ),
     *             @OA\Property(property="message", type="string", example="Region retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Region not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Region not found")
     *         )
     *     )
     * )
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $countryCode = $request->get('country_code');
            $cacheKey = "region:show:{$locale}:{$slug}" . ($countryCode ? ":{$countryCode}" : "");

            $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale, $countryCode, $request) {
                $query = Region::with([
                        'country.continent.translations',
                        'country.translations',
                        'translations',
                        'badges.translations',
                        'badges.status',
                        'articles.translations',
                        'articles.status'
                    ])
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->whereHas('translations', function ($q) use ($slug, $locale) {
                        // Try requested locale first, fallback to English
                        $q->where('slug', $slug)
                          ->whereIn('locale', [$locale, 'en']);
                    });

                // Filter by country if provided
                if ($countryCode) {
                    $query->byCountry($countryCode);
                }

                $region = $query->first([
                    'id',
                    'code',
                    'country_code',
                    'main_image',
                    'status_id'
                ]);

                if (!$region) {
                    return null;
                }

                // Get next and previous regions
                $navigation = $this->getNavigationRegions($region, $locale);

                // Parent levels' published beach counts for the location pill tabs.
                PublishedBeachCounts::loadOnParents($region, ['country', 'country.continent']);

                $resource = new RegionResource($region);
                $resourceArray = $resource->toArray($request);

                // Add navigation to response
                $resourceArray['navigation'] = $navigation;

                return $resourceArray;
            });

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Region retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the region',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/regions/{slug}/weather",
     *     operationId="getRegionWeather",
     *     summary="Get current weather for a region",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific region. Weather data is cached for 1 hour.",
     *     tags={"Regions"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Region slug (e.g., dalmatia, istria)",
     *         @OA\Schema(type="string", example="dalmatia")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Country code to disambiguate regions with same name (e.g., HR, SI)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Weather data retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="region_id", type="integer", example=1),
     *                 @OA\Property(property="region_slug", type="string", example="dalmatia"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="string", example="43.5147"),
     *                     @OA\Property(property="longitude", type="string", example="16.4435")
     *                 ),
     *                 @OA\Property(
     *                     property="weather",
     *                     type="object",
     *                     nullable=true,
     *                     description="Current weather data from OpenWeatherMap (cached for 1 hour)",
     *                     @OA\Property(property="temperature", type="number", format="float", example=9.9),
     *                     @OA\Property(property="feels_like", type="number", format="float", example=8.7),
     *                     @OA\Property(property="temp_min", type="number", format="float", example=9.9),
     *                     @OA\Property(property="temp_max", type="number", format="float", example=9.9),
     *                     @OA\Property(property="pressure", type="integer", example=1014),
     *                     @OA\Property(property="humidity", type="integer", example=58),
     *                     @OA\Property(property="condition", type="string", example="Clouds"),
     *                     @OA\Property(property="description", type="string", example="broken clouds"),
     *                     @OA\Property(property="icon", type="string", example="04d"),
     *                     @OA\Property(property="icon_url", type="string", example="https://openweathermap.org/img/wn/04d@2x.png"),
     *                     @OA\Property(property="wind_speed", type="number", format="float", example=2.52),
     *                     @OA\Property(property="wind_deg", type="integer", example=27),
     *                     @OA\Property(property="clouds", type="integer", example=60),
     *                     @OA\Property(property="visibility", type="integer", example=10000),
     *                     @OA\Property(property="sunrise", type="string", example="06:13"),
     *                     @OA\Property(property="sunset", type="string", example="15:22"),
     *                     @OA\Property(property="location_name", type="string", example="Dalmatia")
     *                 ),
     *                 @OA\Property(property="sea_temperature", type="number", format="float", example=23.5, nullable=true),
     *                 @OA\Property(property="cached_at", type="string", format="date-time", example="2025-11-29T10:17:50+00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Region not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Region not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Region has no coordinates",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Region has no coordinates for weather data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Weather service unavailable",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Weather data temporarily unavailable")
     *         )
     *     )
     * )
     */
    public function weather(string $slug, Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $countryCode = $request->get('country_code');

            // Find region by slug
            $query = Region::with('translations')
                ->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)
                      ->whereIn('locale', [$locale, 'en']);
                });

            // Filter by country if provided
            if ($countryCode) {
                $query->byCountry($countryCode);
            }

            $region = $query->first(['id', 'code', 'country_code', 'latitude', 'longitude']);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            // Get region slug in current locale
            $regionSlug = $this->getRegionSlug($region);

            // Use ProvidesWeather trait to build response
            return $this->buildWeatherResponse($region, 'region', $regionSlug);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving weather data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get region slug in current locale
     */
    private function getRegionSlug($region): ?string
    {
        $locale = app()->getLocale();

        $translation = $region->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $region->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }

    /**
     * Get next and previous regions based on country and ID order
     * Previous = highest ID < current ID in same country
     * Next = lowest ID > current ID in same country
     */
    private function getNavigationRegions(Region $currentRegion, string $locale): array
    {
        // Get previous region: highest ID < current ID in same country
        $previousRegion = DB::select("
            SELECT
                r.id,
                r.code,
                r.country_code,
                r.latitude,
                r.longitude,
                r.main_image,
                COALESCE(rt.name, rt_en.name) as name,
                COALESCE(rt.slug, rt_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug
            FROM region r
            LEFT JOIN region_translation rt ON r.id = rt.region_id AND rt.locale = ?
            LEFT JOIN region_translation rt_en ON r.id = rt_en.region_id AND rt_en.locale = 'en'
            LEFT JOIN countries ON r.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON r.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON r.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            WHERE r.country_code = ?
              AND r.id < ?
              AND r.status_id = 1
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.region_id = r.id
                    AND b.status_id = 1
              )
            ORDER BY r.id DESC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $locale,
            $currentRegion->country_code,
            $currentRegion->id,
        ]);

        // Get next region: lowest ID > current ID in same country
        $nextRegion = DB::select("
            SELECT
                r.id,
                r.code,
                r.country_code,
                r.latitude,
                r.longitude,
                r.main_image,
                COALESCE(rt.name, rt_en.name) as name,
                COALESCE(rt.slug, rt_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug
            FROM region r
            LEFT JOIN region_translation rt ON r.id = rt.region_id AND rt.locale = ?
            LEFT JOIN region_translation rt_en ON r.id = rt_en.region_id AND rt_en.locale = 'en'
            LEFT JOIN countries ON r.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON r.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON r.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            WHERE r.country_code = ?
              AND r.id > ?
              AND r.status_id = 1
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.region_id = r.id
                    AND b.status_id = 1
              )
            ORDER BY r.id ASC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $locale,
            $currentRegion->country_code,
            $currentRegion->id,
        ]);

        // Format the results
        $previous = isset($previousRegion[0]) ? [
            'id' => $previousRegion[0]->id,
            'name' => $previousRegion[0]->name,
            'slug' => $previousRegion[0]->slug,
            'latitude' => $previousRegion[0]->latitude,
            'longitude' => $previousRegion[0]->longitude,
            'main_image' => $previousRegion[0]->main_image,
            'location' => [
                'continent_slug' => $previousRegion[0]->continent_slug,
                'country_slug' => $previousRegion[0]->country_slug,
            ],
        ] : null;

        $next = isset($nextRegion[0]) ? [
            'id' => $nextRegion[0]->id,
            'name' => $nextRegion[0]->name,
            'slug' => $nextRegion[0]->slug,
            'latitude' => $nextRegion[0]->latitude,
            'longitude' => $nextRegion[0]->longitude,
            'main_image' => $nextRegion[0]->main_image,
            'location' => [
                'continent_slug' => $nextRegion[0]->continent_slug,
                'country_slug' => $nextRegion[0]->country_slug,
            ],
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }
}

