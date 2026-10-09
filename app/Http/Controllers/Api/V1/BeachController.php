<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachResource;
use App\Http\Resources\BeachFooterResource;
use App\Models\Beach;
use App\Services\BeachFilterService;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Beaches",
 *     description="API Endpoints for managing beaches"
 * )
 */
class BeachController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    use CacheAware;

    protected BeachFilterService $filterService;

    public function __construct(BeachFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches/map-bounds",
     *     operationId="getBeachesMapBounds",
     *     summary="Get map bounds for all active beaches",
     *     description="Returns only the bounding box and center coordinates of all active beaches (status_id=1). Used for initial map load to calculate proper zoom and center. Does not return beach data. This endpoint is heavily cached (30 days TTL) and cache is automatically cleared when beaches are updated.",
     *     tags={"Beaches"},
     *     @OA\Response(
     *         response=200,
     *         description="Map bounds calculated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/BeachMapBoundsResponse"),
     *         @OA\Header(
     *             header="X-Cache-Status",
     *             description="Cache status: HIT (from cache), MISS (from database), or BYPASS (not cached)",
     *             @OA\Schema(type="string", enum={"HIT", "MISS", "BYPASS"}, example="HIT")
     *         ),
     *         @OA\Header(
     *             header="X-Cache-Key",
     *             description="The cache key used for this request",
     *             @OA\Schema(type="string", example="beaches:map_bounds")
     *         ),
     *         @OA\Header(
     *             header="X-Response-Time",
     *             description="Response time in milliseconds",
     *             @OA\Schema(type="string", example="2.45ms")
     *         ),
     *         @OA\Header(
     *             header="X-Locale",
     *             description="The locale used for this response",
     *             @OA\Schema(type="string", example="en")
     *         )
     *     )
     * )
     */
    public function mapBounds()
    {
        $cacheKey = 'beaches:map_bounds';

        $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
            $geoData = Beach::where('status_id', 1)
                ->selectRaw('
                    ST_Y(ST_Centroid(ST_Collect(location))) as center_lat,
                    ST_X(ST_Centroid(ST_Collect(location))) as center_lng,
                    MIN(ST_Y(location)) as min_lat,
                    MAX(ST_Y(location)) as max_lat,
                    MIN(ST_X(location)) as min_lng,
                    MAX(ST_X(location)) as max_lng,
                    COUNT(*) as total
                ')
                ->whereNotNull('location')
                ->first();

            return [
                'center' => [
                    'latitude' => $geoData?->center_lat ? (float) $geoData->center_lat : null,
                    'longitude' => $geoData?->center_lng ? (float) $geoData->center_lng : null,
                ],
                'bounding_box' => [
                    'min_latitude' => $geoData?->min_lat ? (float) $geoData->min_lat : null,
                    'min_longitude' => $geoData?->min_lng ? (float) $geoData->min_lng : null,
                    'max_latitude' => $geoData?->max_lat ? (float) $geoData->max_lat : null,
                    'max_longitude' => $geoData?->max_lng ? (float) $geoData->max_lng : null,
                ],
                'total_beaches' => $geoData?->total ? (int) $geoData->total : 0,
            ];
        });

        return response()->json($data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches",
     *     operationId="getBeaches",
     *     summary="List all beaches with optional filters",
     *     tags={"Beaches"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Beaches per page (default 20, max 100). Pages are ordered by `sort`; meta has total, current_page, last_page.",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20, example=16)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number (default 1)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="popular (most viewed first, default) or newest",
     *         required=false,
     *         @OA\Schema(type="string", enum={"popular","newest"}, default="popular")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Deprecated alias of per_page (max 100). Before 2026-10-09 the default was 5000 and `page` was ignored.",
     *         required=false,
     *         @OA\Schema(type="integer", example=50, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Alias of city_code (same for region / island → region_code / island_code). Codes or ids, not slugs.",
     *         required=false,
     *         @OA\Schema(type="string", example="murter")
     *     ),
     *     @OA\Parameter(
     *         name="continent_code",
     *         in="query",
     *         description="Filter by continent code(s) or ID(s). Supports: single value (continent_code=EU), comma-separated (continent_code=EU,AS), or array (continent_code[]=EU&continent_code[]=AS). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="EU,AS")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country code(s) or ID(s); `country_code` is accepted as an alias. Supports: single value (country=HR), comma-separated (country=HR,SI,IT), or array (country[]=HR&country[]=SI). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="HR,SI,IT")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Filter by region code(s) or ID(s). Supports: single value (region_code=algarve), comma-separated (region_code=algarve,lisbon), or array (region_code[]=algarve&region_code[]=lisbon). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="algarve")
     *     ),
     *     @OA\Parameter(
     *         name="island_code",
     *         in="query",
     *         description="Filter by island code(s) or ID(s). Supports: single value (island_code=madeira), comma-separated (island_code=madeira,azores), or array (island_code[]=madeira&island_code[]=azores). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="madeira")
     *     ),
     *     @OA\Parameter(
     *         name="city_code",
     *         in="query",
     *         description="Filter by city code(s) or ID(s). Supports: single value (city_code=sukosan), comma-separated (city_code=sukosan,zadar), or array (city_code[]=sukosan&city_code[]=zadar). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sukosan")
     *     ),
     *     @OA\Parameter(
     *         name="waterbody",
     *         in="query",
     *         description="Filter by waterbody code(s) or ID(s). Supports: single value (waterbody=sea), comma-separated (waterbody=sea,lake), or array (waterbody[]=sea&waterbody[]=lake). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sea,lake")
     *     ),
     *     @OA\Parameter(
     *         name="beach_type",
     *         in="query",
     *         description="Filter by beach type code(s) or ID(s). Supports: single value (beach_type=sand), comma-separated (beach_type=sand,pebble), or array (beach_type[]=sand&beach_type[]=pebble). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="sand,pebble")
     *     ),
     *     @OA\Parameter(
     *         name="user_type",
     *         in="query",
     *         description="Filter by user type code(s) or ID(s). Supports: single value (user_type=family), comma-separated (user_type=family,couple), or array (user_type[]=family&user_type[]=couple). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="family,couple")
     *     ),
     *     @OA\Parameter(
     *         name="parking",
     *         in="query",
     *         description="Filter by parking availability (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="pool_type",
     *         in="query",
     *         description="Filter by pool type: indoor and/or outdoor (comma-separated, e.g. pool_type=indoor,outdoor matches either). Based on indoor_pools_count / outdoor_pools_count > 0.",
     *         required=false,
     *         @OA\Schema(type="string", example="indoor")
     *     ),
     *     @OA\Parameter(
     *         name="has_swimming_pool",
     *         in="query",
     *         description="Filter by swimming pool availability (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of beaches with map center coordinates",
     *         @OA\JsonContent(ref="#/components/schemas/BeachListResponseSchema")
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Generate cache key based on filters and locale
        $locale = app()->getLocale();
        $filterSuffix = $this->filterService->generateCacheKeySuffix($request);
        $cacheKey = "beaches:index:{$locale}:{$filterSuffix}";

        // Cache the already-transformed array (not Eloquent models).
        // Caching Eloquent collections with eager loading causes huge
        // serialized blobs (20-28MB per key) that exhaust PHP memory
        // on unserialize. By resolving BeachResource before caching,
        // we store only the flat JSON-able array (~5-10x smaller).
        // Server-side pagination (owner 2026-10-09: listing pages downloaded every beach and paged in the
        // browser). `limit` stays an alias of `per_page` for older callers; at most 100 per page.
        $perPage = max(1, min(self::MAX_PER_PAGE, (int) $request->input('per_page', $request->input('limit', self::DEFAULT_PER_PAGE))));
        $page = max(1, (int) $request->input('page', 1));
        $sort = $request->input('sort') === 'newest' ? 'newest' : 'popular';

        $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($request, $perPage, $page, $sort) {

            // Only load relationships that are actually used in BeachResource
            $query = Beach::where('status_id', 1)
                ->with([
                    'translations',
                    'continent.translations',
                    'country.translations',
                    'region.translations',
                    'city.translations',
                    'island.translations',
                    'media' => function ($query) {
                        $query->where('status_id', 1)
                              ->with('mediaCollection')
                              ->orderBy('is_primary', 'desc')
                              ->orderBy('order')
                              ->orderBy('created_at');
                    },
                    ...\App\Support\BeachCardFeatures::RELATIONS,
                    'sportingActivities.translations',
                    'culinaryOffers.translations',
                    'naturalLocationConditions.translations',
                    'occupancyRate.translations',
                ]);

            // Apply filters
            $query = $this->filterService->applyFilters($query, $request);

            // Calculate center coordinates and bounding box using PostGIS on all filtered beaches
            $centerQuery = clone $query;
            $geoData = $centerQuery
                ->selectRaw('
                    ST_Y(ST_Centroid(ST_Collect(location))) as center_lat,
                    ST_X(ST_Centroid(ST_Collect(location))) as center_lng,
                    MIN(ST_Y(location)) as min_lat,
                    MAX(ST_Y(location)) as max_lat,
                    MIN(ST_X(location)) as min_lng,
                    MAX(ST_X(location)) as max_lng
                ')
                ->whereNotNull('location')
                ->first();

            $total = (clone $query)->count();
            // Stable order, so pages never overlap or skip beaches.
            $sort === 'newest'
                ? $query->orderByRaw('created_at DESC NULLS LAST')->orderByDesc('id')
                : $query->orderByDesc('beach_views')->orderBy('id');
            $beaches = $query->forPage($page, $perPage)->get();

            return [
                'beaches' => BeachResource::collection($beaches)->resolve(),
                'meta' => [
                    'center' => [
                        'latitude' => $geoData?->center_lat ? (float) $geoData->center_lat : null,
                        'longitude' => $geoData?->center_lng ? (float) $geoData->center_lng : null,
                    ],
                    'bounding_box' => [
                        'min_latitude' => $geoData?->min_lat ? (float) $geoData->min_lat : null,
                        'min_longitude' => $geoData?->min_lng ? (float) $geoData->min_lng : null,
                        'max_latitude' => $geoData?->max_lat ? (float) $geoData->max_lat : null,
                        'max_longitude' => $geoData?->max_lng ? (float) $geoData->max_lng : null,
                    ],
                    'total_beaches' => $total,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                    'sort' => $sort,
                ]
            ];
        });

        return response()->json([
            'data' => $data['beaches'],
            'meta' => $data['meta'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/beaches",
     *     operationId="createBeach",
     *     summary="Create a new beach (admin only)",
     *     tags={"Beaches"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "latitude", "longitude", "translations"},
     *             @OA\Property(property="name", type="string", example="Fallback beach name"),
     *             @OA\Property(property="latitude", type="number", format="float", example=45.1234567),
     *             @OA\Property(property="longitude", type="number", format="float", example=13.9876543),
     *             @OA\Property(
     *                 property="translations",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="locale", type="string", example="en"),
     *                     @OA\Property(property="name", type="string", example="Children's Paradise Beach"),
     *                     @OA\Property(property="description", type="string", example="A beach perfect for families."),
     *                     @OA\Property(property="slug", type="string", example="childrens-paradise-beach")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Beach created",
     *         @OA\JsonContent(ref="#/components/schemas/BeachSchema")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden - admin only"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'status_id' => 'nullable|exists:status,id',
            'user_id' => 'nullable|exists:users,id',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|size:2',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
            'translations.*.slug' => 'nullable|string|max:255',
        ]);

        $beach = Beach::create([
            'name' => $data['name'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'status_id' => $data['status_id'] ?? 1,
            'user_id' => $data['user_id'] ?? null,
        ]);

        foreach ($data['translations'] as $trans) {
            $beach->translations()->create([
                'locale' => $trans['locale'],
                'name' => $trans['name'],
                'description' => $trans['description'] ?? null,
                'slug' => $trans['slug'] ?? null,
            ]);
        }

        // Only load relationships that are actually used in BeachResource
        return new BeachResource($beach->load([
            'translations',
            'continent.translations',
            'country.translations',
            'region.translations',
            'city.translations',
            'island.translations',
            'media' => function ($query) {
                $query->where('status_id', 1)
                      ->with('mediaCollection')
                      ->orderBy('is_primary', 'desc')
                      ->orderBy('order')
                      ->orderBy('created_at');
            }
        ]));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches/{identifier}",
     *     operationId="getBeachByIdentifier",
     *     summary="Get a single beach by ID or slug",
     *     tags={"Beaches"},
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         required=true,
     *         description="Beach ID (integer) or slug (string)",
     *         @OA\Schema(
     *             oneOf={
     *                 @OA\Schema(type="integer"),
     *                 @OA\Schema(type="string")
     *             }
     *         )
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Beach found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BeachSchema"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */
    public function show($identifier)
    {
        // Build query with all necessary relationships
        $query = Beach::where('status_id', 1)
            ->with([
                'translations',
                'continent.translations',
                'country.translations',
                'region.translations',
                'city.translations',
                'island.translations',
                'media' => function ($query) {
                    $query->where('status_id', 1)
                          ->with('mediaCollection')
                          ->orderBy('is_primary', 'desc')
                          ->orderBy('order')
                          ->orderBy('created_at');
                }
            ]);

        // Check if identifier is numeric (ID) or string (slug)
        if (is_numeric($identifier)) {
            $beach = $query->findOrFail($identifier);
        } else {
            // Find by slug using translations table
            $beach = $query->whereHas('translations', function ($q) use ($identifier) {
                $q->where('slug', $identifier);
            })->firstOrFail();
        }

        return new BeachResource($beach);
    }

    /**
     * Get most visited beaches with optional geographic filters.
     *
     * Returns beaches ordered by beach_views counter (descending). Only returns active beaches (status_id = 1).
     * Supports filtering by continent, country, region, city, or island. Results are cached for 24 hours.
     *
     * @OA\Get(
     *     path="/api/v1/beaches/most-visited",
     *     operationId="getMostVisitedBeaches",
     *     summary="Get most visited beaches",
     *     description="Returns the most visited beaches ordered by beach_views counter. Supports filtering by continent, country, region, city, or island. Only returns active beaches (status_id = 1). Cached for 24 hours for optimal performance.",
     *     tags={"Beaches"},
     *     @OA\Parameter(
     *         name="continent_code",
     *         in="query",
     *         description="Filter by continent code (e.g., EU, AS, AF, NA, SA, OC, AN)",
     *         required=false,
     *         @OA\Schema(type="string", example="EU")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Filter by ISO country code (e.g., HR, IT, SI, GR, ES, PT, FR)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="query",
     *         description="Filter by region code (e.g., dalmatia-split, kvarner, istra)",
     *         required=false,
     *         @OA\Schema(type="string", example="dalmatia-split")
     *     ),
     *     @OA\Parameter(
     *         name="city_code",
     *         in="query",
     *         description="Filter by city code (e.g., split, dubrovnik, zadar)",
     *         required=false,
     *         @OA\Schema(type="string", example="split")
     *     ),
     *     @OA\Parameter(
     *         name="island_code",
     *         in="query",
     *         description="Filter by island code (e.g., hvar, brac, krk)",
     *         required=false,
     *         @OA\Schema(type="string", example="hvar")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of beaches to return (default: 20, max: 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20, example=20)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved most visited beaches",
     *         @OA\JsonContent(ref="#/components/schemas/BeachMostVisitedResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - invalid parameters"
     *     )
     * )
     */
    public function mostVisited(Request $request)
    {
        // Generate cache key based on filters and locale
        $locale = app()->getLocale();
        $limit = min((int) $request->input('limit', 20), 100); // Max 100

        $cacheKeySuffix = collect([
            'continent' => $request->input('continent_code'),
            'country' => $request->input('country_code'),
            'region' => $request->input('region_code'),
            'city' => $request->input('city_code'),
            'island' => $request->input('island_code'),
            'limit' => $limit,
        ])->filter()->map(fn($value, $key) => "{$key}:{$value}")->implode('_');

        $cacheKey = "beaches:most_visited:{$locale}:" . ($cacheKeySuffix ?: 'all');

        // Cache for 24 hours (86400 seconds)
        // Cache the resolved resource array, not Eloquent models, to keep
        // Redis keys small and avoid memory exhaustion on unserialize.
        $data = $this->cacheRemember($cacheKey, 86400, function () use ($request, $limit) {
            $query = Beach::where('status_id', 1)
                ->with([
                    'translations',
                    'continent.translations',
                    'country.translations',
                    'region.translations',
                    'city.translations',
                    'island.translations',
                    'media' => function ($query) {
                        $query->where('status_id', 1)
                              ->with('mediaCollection')
                              ->orderBy('is_primary', 'desc')
                              ->orderBy('order')
                              ->orderBy('created_at');
                    },
                    ...\App\Support\BeachCardFeatures::RELATIONS,
                    'sportingActivities.translations',
                    'culinaryOffers.translations',
                    'naturalLocationConditions.translations',
                    'occupancyRate.translations',
                ]);

            // Apply geographic filters
            if ($request->has('continent_code')) {
                $query->where('continent_code', strtoupper($request->input('continent_code')));
            }

            if ($request->has('country_code')) {
                $query->where('country_code', strtoupper($request->input('country_code')));
            }

            if ($request->has('region_code')) {
                $query->whereHas('region', function ($q) use ($request) {
                    $q->where('code', $request->input('region_code'));
                });
            }

            if ($request->has('city_code')) {
                $query->whereHas('city', function ($q) use ($request) {
                    $q->where('code', $request->input('city_code'));
                });
            }

            if ($request->has('island_code')) {
                $query->whereHas('island', function ($q) use ($request) {
                    $q->where('code', $request->input('island_code'));
                });
            }

            // Order by beach_views descending and limit results
            $beaches = $query->orderBy('beach_views', 'desc')
                ->limit($limit)
                ->get();

            return [
                'beaches' => BeachResource::collection($beaches)->resolve(),
                'total' => $beaches->count(),
                'limit' => $limit,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data['beaches'],
            'message' => 'Most visited beaches retrieved successfully',
            'meta' => [
                'total' => $data['total'],
                'limit' => $data['limit'],
            ],
        ], 200);
    }

    /**
     * Get the 6 most visited beaches for the footer.
     *
     * Lightweight endpoint: returns only the localized name + the location
     * slugs needed to build the beach detail URL on the frontend. Ordered by
     * the beach_views counter (descending), active beaches only. Cached for
     * 24 hours per locale.
     *
     * @OA\Get(
     *     path="/api/v1/beaches/footer",
     *     operationId="getBeachesForFooter",
     *     summary="List most visited beaches for the footer",
     *     description="Returns the 6 most visited beaches (by beach_views counter) with only the fields needed to render footer links: localized name, slug, and location slugs for URL building. Active beaches only. Localized by Accept-Language with English fallback.",
     *     tags={"Beaches"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Footer beaches retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BeachFooter")),
     *             @OA\Property(property="message", type="string", example="Footer beaches retrieved successfully")
     *         ),
     *         @OA\Header(header="X-Cache-Status", ref="#/components/responses/CacheHeaders/headers/X-Cache-Status"),
     *         @OA\Header(header="X-Cache-Key", ref="#/components/responses/CacheHeaders/headers/X-Cache-Key"),
     *         @OA\Header(header="X-Response-Time", ref="#/components/responses/CacheHeaders/headers/X-Response-Time"),
     *         @OA\Header(header="X-Locale", ref="#/components/responses/CacheHeaders/headers/X-Locale")
     *     )
     * )
     */
    public function footerMostVisited()
    {
        $locale = app()->getLocale();
        $cacheKey = "beaches:footer:{$locale}";

        // Cache the resolved resource array, not Eloquent models.
        $data = $this->cacheRemember($cacheKey, 86400, function () {
            $beaches = Beach::where('status_id', 1)
                ->with([
                    'translations',
                    'continent.translations',
                    'country.translations',
                    'region.translations',
                    'island.translations',
                    'city.translations',
                ])
                ->orderBy('beach_views', 'desc')
                ->orderBy('id')
                ->limit(6)
                ->get();

            return BeachFooterResource::collection($beaches)->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Footer beaches retrieved successfully',
        ], 200);
    }
}
