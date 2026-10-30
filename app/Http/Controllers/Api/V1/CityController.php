<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
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
 *     name="Cities",
 *     description="API Endpoints for cities. All endpoints support filtering by country_code, region_code, and island_code parameters. Only cities with active beaches (status_id = 1) are returned."
 * )
 */
class CityController extends Controller
{
    use CacheAware, ProvidesWeather;

    /**
     * @OA\Get(
     *     path="/api/v1/cities",
     *     operationId="getCities",
     *     tags={"Cities"},
     *     summary="Get all cities that have beaches",
     *     description="Returns a list of cities that have at least one active beach (status_id = 1). Supports multiple filter parameters that can be combined: country_code (e.g., HR, IT), region_code (e.g., istria, kvarner), island_code (e.g., krk, rab). Returns minimal data: id, code, name, slug, country_code, region_id, island_id, beaches_count, accommodation_count, and country object (without continent, badges, articles).",
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Filter by country code (e.g., SI, HR, IT)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Filter by region code (e.g., istria, kvarner, dalmatia-split)",
     *         required=false,
     *         @OA\Schema(type="string", example="istria")
     *     ),
     *     @OA\Parameter(
     *         name="island_code",
     *         in="query",
     *         description="Filter by island code (e.g., krk, rab, hvar)",
     *         required=false,
     *         @OA\Schema(type="string", example="krk")
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
     *                 @OA\Items(ref="#/components/schemas/City")
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Cities retrieved successfully"
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

            // Get filter parameters (all optional, can be combined)
            $countryFilter = $request->get('country_code');  // e.g., HR, IT, SI
            $regionFilter = $request->get('region_code');    // e.g., istria, kvarner, dalmatia-split
            $islandFilter = $request->get('island_code');    // e.g., krk, rab, hvar

            // Whole response cached per locale and filter (ApiResponseCache: JSON, not models; cleared on content changes).
            return \App\Support\ApiResponseCache::json('cities.index', ['country_code' => $countryFilter, 'region_code' => $regionFilter, 'island_code' => $islandFilter], \App\Support\ApiResponseCache::TTL_REFERENCE, function () use ($countryFilter, $regionFilter, $islandFilter) {
                $query = City::with([
                        'country.continent.translations',
                        'country.translations',
                        'region.translations',
                        'island.translations',
                        'translations'
                    ])
                    ->withBeaches()  // Only cities with active beaches (status_id = 1)
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }]);

                // Apply geographic filters if provided (filters can be combined)
                if ($countryFilter) {
                    $query->byCountry($countryFilter);  // Filter by country code
                }
                if ($regionFilter) {
                    $query->byRegion($regionFilter);    // Filter by region code or ID
                }
                if ($islandFilter) {
                    $query->byIsland($islandFilter);    // Filter by island code or ID
                }

                $cities = $query->get([
                    'id',
                    'code',
                    'country_code',
                    'region_id',
                    'island_id',
                    'main_image',
                    'status_id'
                ]);

                return [
                    'success' => true,
                    'data' => CityResource::collection($cities),
                    'message' => 'Cities retrieved successfully'
                ];
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving cities',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cities/{slug}",
     *     operationId="getCity",
     *     tags={"Cities"},
     *     summary="Get a specific city",
     *     description="Returns a specific city by slug with full details including country, region, badges, articles, and navigation. Supports filter parameters (country_code, region_code, island_code) to ensure the city matches the specified criteria - returns 404 if filters don't match.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="City slug (e.g., split, dubrovnik, pula)",
     *         required=true,
     *         @OA\Schema(type="string", example="split")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Filter by country code (e.g., SI, HR, IT)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Filter by region code (e.g., istria, kvarner, dalmatia-split)",
     *         required=false,
     *         @OA\Schema(type="string", example="istria")
     *     ),
     *     @OA\Parameter(
     *         name="island_code",
     *         in="query",
     *         description="Filter by island code (e.g., krk, rab, hvar)",
     *         required=false,
     *         @OA\Schema(type="string", example="krk")
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
     *                     @OA\Schema(ref="#/components/schemas/City"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="navigation",
     *                             type="object",
     *                             description="Navigation to previous and next cities with beaches in the same island, region, or country",
     *                             @OA\Property(
     *                                 property="previous",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Split"),
     *                                 @OA\Property(property="slug", type="string", example="split"),
     *                                 @OA\Property(property="latitude", type="string", example="43.5"),
     *                                 @OA\Property(property="longitude", type="string", example="16.4"),
     *                                 @OA\Property(property="main_image", type="string", nullable=true, example="img/cities/split.jpg"),
     *                                 @OA\Property(
     *                                     property="location",
     *                                     type="object",
     *                                     @OA\Property(property="continent_slug", type="string", example="europe"),
     *                                     @OA\Property(property="country_slug", type="string", example="croatia"),
     *                                     @OA\Property(property="region_slug", type="string", example="dalmatia"),
     *                                     @OA\Property(property="island_slug", type="string", nullable=true, example=null)
     *                                 )
     *                             ),
     *                             @OA\Property(
     *                                 property="next",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=3),
     *                                 @OA\Property(property="name", type="string", example="Dubrovnik"),
     *                                 @OA\Property(property="slug", type="string", example="dubrovnik"),
     *                                 @OA\Property(property="latitude", type="string", example="42.6"),
     *                                 @OA\Property(property="longitude", type="string", example="18.1"),
     *                                 @OA\Property(property="main_image", type="string", nullable=true, example="img/cities/dubrovnik.jpg"),
     *                                 @OA\Property(
     *                                     property="location",
     *                                     type="object",
     *                                     @OA\Property(property="continent_slug", type="string", example="europe"),
     *                                     @OA\Property(property="country_slug", type="string", example="croatia"),
     *                                     @OA\Property(property="region_slug", type="string", example="dalmatia"),
     *                                     @OA\Property(property="island_slug", type="string", nullable=true, example=null)
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 }
     *             ),
     *             @OA\Property(property="message", type="string", example="City retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="City not found")
     *         )
     *     )
     * )
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();

            // Get filter parameters (optional - used to validate city matches criteria)
            $countryCode = $request->get('country_code');  // e.g., HR, IT, SI
            $regionCode = $request->get('region_code');    // e.g., istria, kvarner, dalmatia-split
            $islandCode = $request->get('island_code');    // e.g., krk, rab, hvar

            // Build cache key based on active filters
            $filterParts = [];
            if ($countryCode) $filterParts[] = $countryCode;
            if ($regionCode) $filterParts[] = $regionCode;
            if ($islandCode) $filterParts[] = $islandCode;
            $cacheKey = "city:show:{$locale}:{$slug}" . (count($filterParts) > 0 ? ":" . implode(':', $filterParts) : "");

            $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($request, $slug, $locale, $countryCode, $regionCode, $islandCode) {
                $query = City::with([
                        'country.continent.translations',
                        'country.translations',
                        'region.translations',
                        'island.translations',
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

                // Apply filters if provided - returns 404 if city doesn't match criteria
                if ($countryCode) {
                    $query->byCountry($countryCode);  // Validate city is in specified country
                }
                if ($regionCode) {
                    $query->byRegion($regionCode);    // Validate city is in specified region
                }
                if ($islandCode) {
                    $query->byIsland($islandCode);    // Validate city is on specified island
                }

                $city = $query->first([
                    'id',
                    'code',
                    'country_code',
                    'region_id',
                    'island_id',
                    'main_image',
                    'status_id'
                ]);

                if (!$city) {
                    return null;
                }

                // Get next and previous cities
                $navigation = $this->getNavigationCities($city, $locale);

                // Parent levels' published beach counts for the location pill tabs.
                PublishedBeachCounts::loadOnParents($city, ['country', 'country.continent', 'region', 'island']);

                $resource = new CityResource($city);
                $resourceArray = $resource->toArray($request);

                // Add navigation to response
                $resourceArray['navigation'] = $navigation;

                return $resourceArray;
            });

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'City not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'City retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the city',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/cities/{slug}/weather",
     *     operationId="getCityWeather",
     *     summary="Get current weather for a city",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific city. Weather data is cached for 1 hour. Supports filter parameters (country_code, region_code, island_code) to disambiguate cities with the same name or ensure the city matches specified criteria.",
     *     tags={"Cities"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="City slug (e.g., split, dubrovnik, pula)",
     *         @OA\Schema(type="string", example="split")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Country code to disambiguate cities with same name (e.g., HR, SI)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Region code to filter cities (e.g., istria, kvarner)",
     *         required=false,
     *         @OA\Schema(type="string", example="istria")
     *     ),
     *     @OA\Parameter(
     *         name="island_code",
     *         in="query",
     *         description="Island code to filter cities (e.g., krk, rab)",
     *         required=false,
     *         @OA\Schema(type="string", example="krk")
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
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="city_slug", type="string", example="split"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="string", example="43.5081"),
     *                     @OA\Property(property="longitude", type="string", example="16.4402")
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
     *                     @OA\Property(property="location_name", type="string", example="Split")
     *                 ),
     *                 @OA\Property(property="sea_temperature", type="number", format="float", example=23.5, nullable=true),
     *                 @OA\Property(property="cached_at", type="string", format="date-time", example="2025-11-29T10:17:50+00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="City not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="City has no coordinates",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="City has no coordinates for weather data")
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

            // Get filter parameters (optional - used to disambiguate cities with same name)
            $countryCode = $request->get('country_code');  // e.g., HR, IT, SI
            $regionCode = $request->get('region_code');    // e.g., istria, kvarner, dalmatia-split
            $islandCode = $request->get('island_code');    // e.g., krk, rab, hvar

            // Find city by slug with optional filters
            $query = City::with('translations')
                ->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)
                      ->whereIn('locale', [$locale, 'en']);
                });

            // Apply filters if provided to disambiguate or validate
            if ($countryCode) {
                $query->byCountry($countryCode);  // Ensure city is in specified country
            }
            if ($regionCode) {
                $query->byRegion($regionCode);    // Ensure city is in specified region
            }
            if ($islandCode) {
                $query->byIsland($islandCode);    // Ensure city is on specified island
            }

            $city = $query->first(['id', 'code', 'country_code', 'latitude', 'longitude']);

            if (!$city) {
                return response()->json([
                    'success' => false,
                    'message' => 'City not found'
                ], 404);
            }

            // Get city slug in current locale
            $citySlug = $this->getCitySlug($city);

            // Use ProvidesWeather trait to build response
            return $this->buildWeatherResponse($city, 'city', $citySlug);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving weather data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get city slug in current locale
     */
    private function getCitySlug($city): ?string
    {
        $locale = app()->getLocale();

        $translation = $city->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $city->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }

    /**
     * Get next and previous cities based on region and ID order
     * Previous = highest ID < current ID in same region (or country if no region)
     * Next = lowest ID > current ID in same region (or country if no region)
     */
    private function getNavigationCities(City $currentCity, string $locale): array
    {
        // Determine scope: region if available, otherwise country
        $scopeField = $currentCity->region_id ? 'region_id' : 'country_code';
        $scopeValue = $currentCity->region_id ? $currentCity->region_id : $currentCity->country_code;

        // Get previous city: highest ID < current ID in same scope
        $previousCity = DB::select("
            SELECT
                c.id,
                c.code,
                c.country_code,
                c.region_id,
                c.latitude,
                c.longitude,
                c.main_image,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug,
                COALESCE(region_t.slug, region_t_en.slug) as region_slug,
                COALESCE(island_t.slug, island_t_en.slug) as island_slug
            FROM city c
            LEFT JOIN city_translation ct ON c.id = ct.city_id AND ct.locale = ?
            LEFT JOIN city_translation ct_en ON c.id = ct_en.city_id AND ct_en.locale = 'en'
            LEFT JOIN countries ON c.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON c.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON c.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            LEFT JOIN region_translation region_t ON c.region_id = region_t.region_id AND region_t.locale = ?
            LEFT JOIN region_translation region_t_en ON c.region_id = region_t_en.region_id AND region_t_en.locale = 'en'
            LEFT JOIN island_translation island_t ON c.island_id = island_t.island_id AND island_t.locale = ?
            LEFT JOIN island_translation island_t_en ON c.island_id = island_t_en.island_id AND island_t_en.locale = 'en'
            WHERE c.{$scopeField} = ?
              AND c.id < ?
              AND c.status_id = 1
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.city_id = c.id
                    AND b.status_id = 1
              )
            ORDER BY c.id DESC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $locale,
            $locale,
            $locale,
            $scopeValue,
            $currentCity->id,
        ]);

        // Get next city: lowest ID > current ID in same scope
        $nextCity = DB::select("
            SELECT
                c.id,
                c.code,
                c.country_code,
                c.region_id,
                c.latitude,
                c.longitude,
                c.main_image,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug,
                COALESCE(region_t.slug, region_t_en.slug) as region_slug,
                COALESCE(island_t.slug, island_t_en.slug) as island_slug
            FROM city c
            LEFT JOIN city_translation ct ON c.id = ct.city_id AND ct.locale = ?
            LEFT JOIN city_translation ct_en ON c.id = ct_en.city_id AND ct_en.locale = 'en'
            LEFT JOIN countries ON c.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON c.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON c.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            LEFT JOIN region_translation region_t ON c.region_id = region_t.region_id AND region_t.locale = ?
            LEFT JOIN region_translation region_t_en ON c.region_id = region_t_en.region_id AND region_t_en.locale = 'en'
            LEFT JOIN island_translation island_t ON c.island_id = island_t.island_id AND island_t.locale = ?
            LEFT JOIN island_translation island_t_en ON c.island_id = island_t_en.island_id AND island_t_en.locale = 'en'
            WHERE c.{$scopeField} = ?
              AND c.id > ?
              AND c.status_id = 1
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.city_id = c.id
                    AND b.status_id = 1
              )
            ORDER BY c.id ASC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $locale,
            $locale,
            $locale,
            $scopeValue,
            $currentCity->id,
        ]);

        // Format the results
        $previous = isset($previousCity[0]) ? [
            'id' => $previousCity[0]->id,
            'name' => $previousCity[0]->name,
            'slug' => $previousCity[0]->slug,
            'latitude' => $previousCity[0]->latitude,
            'longitude' => $previousCity[0]->longitude,
            'main_image' => $previousCity[0]->main_image,
            'location' => [
                'continent_slug' => $previousCity[0]->continent_slug,
                'country_slug' => $previousCity[0]->country_slug,
                'region_slug' => $previousCity[0]->region_slug,
                'island_slug' => $previousCity[0]->island_slug,
            ],
        ] : null;

        $next = isset($nextCity[0]) ? [
            'id' => $nextCity[0]->id,
            'name' => $nextCity[0]->name,
            'slug' => $nextCity[0]->slug,
            'latitude' => $nextCity[0]->latitude,
            'longitude' => $nextCity[0]->longitude,
            'main_image' => $nextCity[0]->main_image,
            'location' => [
                'continent_slug' => $nextCity[0]->continent_slug,
                'country_slug' => $nextCity[0]->country_slug,
                'region_slug' => $nextCity[0]->region_slug,
                'island_slug' => $nextCity[0]->island_slug,
            ],
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }
}

