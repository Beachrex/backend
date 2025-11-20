<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachResource;
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
     *         name="limit",
     *         in="query",
     *         description="Maximum number of beaches to return (default: 5000)",
     *         required=false,
     *         @OA\Schema(type="integer", example=50, default=5000)
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
     *         description="Filter by country code(s) or ID(s). Supports: single value (country=HR), comma-separated (country=HR,SI,IT), or array (country[]=HR&country[]=SI). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="HR,SI,IT")
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region code(s) or ID(s). Supports: single value (region=algarve), comma-separated (region=algarve,lisbon), or array (region[]=algarve&region[]=lisbon). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="algarve")
     *     ),
     *     @OA\Parameter(
     *         name="island",
     *         in="query",
     *         description="Filter by island code(s) or ID(s). Supports: single value (island=madeira), comma-separated (island=madeira,azores), or array (island[]=madeira&island[]=azores). Can mix codes and IDs.",
     *         required=false,
     *         @OA\Schema(type="string", example="madeira")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city code(s) or ID(s). Supports: single value (city=sukosan), comma-separated (city=sukosan,zadar), or array (city[]=sukosan&city[]=zadar). Can mix codes and IDs.",
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

        $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($request) {
            $limit = $request->input('limit', 5000);

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
                        $query->where('media_collection_id', 1)
                              ->where('is_primary', true)
                              ->where('status_id', 1);
                    }
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

            $beaches = $query->limit($limit)->get();

            return [
                'beaches' => $beaches,
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
                    'total_beaches' => $beaches->count(),
                ]
            ];
        });

        return BeachResource::collection($data['beaches'])->additional([
            'meta' => $data['meta']
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/beaches",
     *     operationId="createBeach",
     *     summary="Create a new beach",
     *     tags={"Beaches"},
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
                $query->where('media_collection_id', 1)
                      ->where('is_primary', true)
                      ->where('status_id', 1);
            }
        ]));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beaches/{id}",
     *     operationId="getBeachById",
     *     summary="Get a single beach by ID",
     *     tags={"Beaches"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Beach ID",
     *         @OA\Schema(type="integer")
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
    public function show($id)
    {
        // Only load relationships that are actually used in BeachResource
        $beach = Beach::where('status_id', 1)
            ->with([
                'translations',
                'continent.translations',
                'country.translations',
                'region.translations',
                'city.translations',
                'island.translations',
                'media' => function ($query) {
                    $query->where('media_collection_id', 1)
                          ->where('is_primary', true)
                          ->where('status_id', 1);
                }
            ])->findOrFail($id);

        return new BeachResource($beach);
    }
}
