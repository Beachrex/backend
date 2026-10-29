<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IslandResource;
use App\Models\Island;
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
 *     name="Islands",
 *     description="API Endpoints for islands. All endpoints support filtering by country_code and region_code parameters. Only islands with active beaches (status_id = 1) are returned."
 * )
 */
class IslandController extends Controller
{
    use CacheAware, ProvidesWeather;

    /**
     * @OA\Get(
     *     path="/api/v1/islands",
     *     operationId="getIslands",
     *     tags={"Islands"},
     *     summary="Get all islands that have beaches",
     *     description="Returns a list of islands that have at least one active beach (status_id = 1). Can be filtered by country_code and/or region_code. Filters can be combined. Returns minimal data: id, code, name, slug, country_code, region_id, main_image, beaches_count, accommodation_count.",
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
     *         @OA\Schema(type="string", example="kvarner")
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
     *                 @OA\Items(ref="#/components/schemas/Island")
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Islands retrieved successfully"
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

            // Build cache key based on active filters to ensure proper cache segmentation
            $filterParts = [];
            if ($countryFilter) $filterParts[] = "country:{$countryFilter}";
            if ($regionFilter) $filterParts[] = "region:{$regionFilter}";
            $cacheKey = "islands:index:{$locale}:" . (count($filterParts) > 0 ? implode(':', $filterParts) : "all");

            $islands = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($countryFilter, $regionFilter) {
                $query = Island::with([
                        'country.continent.translations',
                        'country.translations',
                        'region.translations',
                        'translations'
                    ])
                    ->withBeaches()  // Only islands with active beaches (status_id = 1)
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

                return $query->get([
                    'id',
                    'code',
                    'country_code',
                    'region_id',
                    'main_image',
                    'status_id'
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => IslandResource::collection($islands),
                'message' => 'Islands retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving islands',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/islands/{slug}",
     *     operationId="getIsland",
     *     tags={"Islands"},
     *     summary="Get a specific island",
     *     description="Returns a specific island by slug with full details including beaches count, badges, and articles. Optional filters (country_code, region_code) can be used to validate the island matches the specified criteria - returns 404 if filters don't match.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Island slug (e.g., krk, brac, hvar)",
     *         required=true,
     *         @OA\Schema(type="string", example="krk")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Optional: Validate island is in specified country (e.g., HR, IT, SI)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Optional: Validate island is in specified region (e.g., kvarner, istria)",
     *         required=false,
     *         @OA\Schema(type="string", example="kvarner")
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
     *                     @OA\Schema(ref="#/components/schemas/Island"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="navigation",
     *                             type="object",
     *                             description="Navigation to previous and next islands with beaches in the same region or country",
     *                             @OA\Property(
     *                                 property="previous",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Krk"),
     *                                 @OA\Property(property="slug", type="string", example="krk"),
     *                                 @OA\Property(property="latitude", type="string", example="45.0"),
     *                                 @OA\Property(property="longitude", type="string", example="14.6"),
     *                                 @OA\Property(property="main_image", type="string", nullable=true, example="img/islands/krk.jpg"),
     *                                 @OA\Property(
     *                                     property="location",
     *                                     type="object",
     *                                     @OA\Property(property="continent_slug", type="string", example="europe"),
     *                                     @OA\Property(property="country_slug", type="string", example="croatia"),
     *                                     @OA\Property(property="region_slug", type="string", example="kvarner")
     *                                 )
     *                             ),
     *                             @OA\Property(
     *                                 property="next",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="id", type="integer", example=3),
     *                                 @OA\Property(property="name", type="string", example="Hvar"),
     *                                 @OA\Property(property="slug", type="string", example="hvar"),
     *                                 @OA\Property(property="latitude", type="string", example="43.1"),
     *                                 @OA\Property(property="longitude", type="string", example="16.4"),
     *                                 @OA\Property(property="main_image", type="string", nullable=true, example="img/islands/hvar.jpg"),
     *                                 @OA\Property(
     *                                     property="location",
     *                                     type="object",
     *                                     @OA\Property(property="continent_slug", type="string", example="europe"),
     *                                     @OA\Property(property="country_slug", type="string", example="croatia"),
     *                                     @OA\Property(property="region_slug", type="string", example="dalmatia")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 }
     *             ),
     *             @OA\Property(property="message", type="string", example="Island retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Island not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Island not found")
     *         )
     *     )
     * )
     */
    public function show(string $slug, Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();

            // Get filter parameters (optional - used to validate island matches criteria)
            $countryCode = $request->get('country_code');  // e.g., HR, IT, SI
            $regionCode = $request->get('region_code');    // e.g., istria, kvarner, dalmatia-split

            // Build cache key based on active filters
            $filterParts = [];
            if ($countryCode) $filterParts[] = $countryCode;
            if ($regionCode) $filterParts[] = $regionCode;
            $cacheKey = "island:show:{$locale}:{$slug}" . (count($filterParts) > 0 ? ":" . implode(':', $filterParts) : "");

            $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale, $request, $countryCode, $regionCode) {
                $query = Island::with([
                        'country.continent.translations',
                        'country.translations',
                        'region.translations',
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

                // Apply filters if provided - returns 404 if island doesn't match criteria
                if ($countryCode) {
                    $query->byCountry($countryCode);  // Validate island is in specified country
                }
                if ($regionCode) {
                    $query->byRegion($regionCode);    // Validate island is in specified region
                }

                $island = $query->first([
                        'id',
                        'code',
                        'country_code',
                        'region_id',
                        'main_image',
                        'status_id'
                    ]);

                if (!$island) {
                    return null;
                }

                // Get next and previous islands
                $navigation = $this->getNavigationIslands($island, $locale);

                // Parent levels' published beach counts for the location pill tabs.
                PublishedBeachCounts::loadOnParents($island, ['country', 'country.continent', 'region']);

                $resource = new IslandResource($island);
                $resourceArray = $resource->toArray($request);

                // Add navigation to response
                $resourceArray['navigation'] = $navigation;

                return $resourceArray;
            });

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Island not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Island retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the island',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/islands/{slug}/weather",
     *     operationId="getIslandWeather",
     *     summary="Get current weather for an island",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific island. Weather data is cached for 1 hour. Optional filters (country_code, region_code) can be used to disambiguate islands with the same name or validate the island matches specified criteria.",
     *     tags={"Islands"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Island slug (e.g., krk, brac, hvar)",
     *         @OA\Schema(type="string", example="hvar")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Optional: Filter by country code to disambiguate (e.g., HR, IT, SI)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Optional: Filter by region code to disambiguate (e.g., kvarner, istria)",
     *         required=false,
     *         @OA\Schema(type="string", example="kvarner")
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
     *                 @OA\Property(property="island_id", type="integer", example=1),
     *                 @OA\Property(property="island_slug", type="string", example="hvar"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="string", example="43.1729"),
     *                     @OA\Property(property="longitude", type="string", example="16.4411")
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
     *                     @OA\Property(property="location_name", type="string", example="Hvar")
     *                 ),
     *                 @OA\Property(property="sea_temperature", type="number", format="float", example=23.5, nullable=true),
     *                 @OA\Property(property="cached_at", type="string", format="date-time", example="2025-11-29T10:17:50+00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Island not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Island not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Island has no coordinates",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Island has no coordinates for weather data")
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

            // Get filter parameters (optional - used to disambiguate islands with same name)
            $countryCode = $request->get('country_code');  // e.g., HR, IT, SI
            $regionCode = $request->get('region_code');    // e.g., istria, kvarner, dalmatia-split

            // Find island by slug with optional filters
            $query = Island::with('translations')
                ->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)
                      ->whereIn('locale', [$locale, 'en']);
                });

            // Apply filters if provided to disambiguate or validate
            if ($countryCode) {
                $query->byCountry($countryCode);  // Ensure island is in specified country
            }
            if ($regionCode) {
                $query->byRegion($regionCode);    // Ensure island is in specified region
            }

            $island = $query->first(['id', 'code', 'latitude', 'longitude']);

            if (!$island) {
                return response()->json([
                    'success' => false,
                    'message' => 'Island not found'
                ], 404);
            }

            // Get island slug in current locale
            $islandSlug = $this->getIslandSlug($island);

            // Use ProvidesWeather trait to build response
            return $this->buildWeatherResponse($island, 'island', $islandSlug);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving weather data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get island slug in current locale
     */
    private function getIslandSlug($island): ?string
    {
        $locale = app()->getLocale();

        $translation = $island->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $island->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }

    /**
     * Get next and previous islands based on region and ID order
     * Previous = highest ID < current ID in same region (or country if no region)
     * Next = lowest ID > current ID in same region (or country if no region)
     */
    private function getNavigationIslands(Island $currentIsland, string $locale): array
    {
        // Determine scope: region if available, otherwise country
        $scopeField = $currentIsland->region_id ? 'region_id' : 'country_code';
        $scopeValue = $currentIsland->region_id ? $currentIsland->region_id : $currentIsland->country_code;

        // Get previous island: highest ID < current ID in same scope
        $previousIsland = DB::select("
            SELECT
                i.id,
                i.code,
                i.country_code,
                i.region_id,
                i.latitude,
                i.longitude,
                i.main_image,
                COALESCE(it.name, it_en.name) as name,
                COALESCE(it.slug, it_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug,
                COALESCE(region_t.slug, region_t_en.slug) as region_slug
            FROM island i
            LEFT JOIN island_translation it ON i.id = it.island_id AND it.locale = ?
            LEFT JOIN island_translation it_en ON i.id = it_en.island_id AND it_en.locale = 'en'
            LEFT JOIN countries ON i.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON i.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON i.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            LEFT JOIN region_translation region_t ON i.region_id = region_t.region_id AND region_t.locale = ?
            LEFT JOIN region_translation region_t_en ON i.region_id = region_t_en.region_id AND region_t_en.locale = 'en'
            WHERE i.{$scopeField} = ?
              AND i.id < ?
              AND i.status_id = 1
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.island_id = i.id
                    AND b.status_id = 1
              )
            ORDER BY i.id DESC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $locale,
            $locale,
            $scopeValue,
            $currentIsland->id,
        ]);

        // Get next island: lowest ID > current ID in same scope
        $nextIsland = DB::select("
            SELECT
                i.id,
                i.code,
                i.country_code,
                i.region_id,
                i.latitude,
                i.longitude,
                i.main_image,
                COALESCE(it.name, it_en.name) as name,
                COALESCE(it.slug, it_en.slug) as slug,
                COALESCE(cont_t.slug, cont_t_en.slug) as continent_slug,
                COALESCE(country_t.slug, country_t_en.slug) as country_slug,
                COALESCE(region_t.slug, region_t_en.slug) as region_slug
            FROM island i
            LEFT JOIN island_translation it ON i.id = it.island_id AND it.locale = ?
            LEFT JOIN island_translation it_en ON i.id = it_en.island_id AND it_en.locale = 'en'
            LEFT JOIN countries ON i.country_code = countries.code
            LEFT JOIN continents ON countries.continent_code = continents.code
            LEFT JOIN continent_translation cont_t ON continents.code = cont_t.continent_code AND cont_t.locale = ?
            LEFT JOIN continent_translation cont_t_en ON continents.code = cont_t_en.continent_code AND cont_t_en.locale = 'en'
            LEFT JOIN country_translation country_t ON i.country_code = country_t.country_code AND country_t.locale = ?
            LEFT JOIN country_translation country_t_en ON i.country_code = country_t_en.country_code AND country_t_en.locale = 'en'
            LEFT JOIN region_translation region_t ON i.region_id = region_t.region_id AND region_t.locale = ?
            LEFT JOIN region_translation region_t_en ON i.region_id = region_t_en.region_id AND region_t_en.locale = 'en'
            WHERE i.{$scopeField} = ?
              AND i.id > ?
              AND i.status_id = 1
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.island_id = i.id
                    AND b.status_id = 1
              )
            ORDER BY i.id ASC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $locale,
            $locale,
            $scopeValue,
            $currentIsland->id,
        ]);

        // Format the results
        $previous = isset($previousIsland[0]) ? [
            'id' => $previousIsland[0]->id,
            'name' => $previousIsland[0]->name,
            'slug' => $previousIsland[0]->slug,
            'latitude' => $previousIsland[0]->latitude,
            'longitude' => $previousIsland[0]->longitude,
            'main_image' => $previousIsland[0]->main_image,
            'location' => [
                'continent_slug' => $previousIsland[0]->continent_slug,
                'country_slug' => $previousIsland[0]->country_slug,
                'region_slug' => $previousIsland[0]->region_slug,
            ],
        ] : null;

        $next = isset($nextIsland[0]) ? [
            'id' => $nextIsland[0]->id,
            'name' => $nextIsland[0]->name,
            'slug' => $nextIsland[0]->slug,
            'latitude' => $nextIsland[0]->latitude,
            'longitude' => $nextIsland[0]->longitude,
            'main_image' => $nextIsland[0]->main_image,
            'location' => [
                'continent_slug' => $nextIsland[0]->continent_slug,
                'country_slug' => $nextIsland[0]->country_slug,
                'region_slug' => $nextIsland[0]->region_slug,
            ],
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }
}

