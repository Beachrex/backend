<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

/**
 * @OA\Tag(
 *     name="Regions",
 *     description="API Endpoints for regions"
 * )
 */
class RegionController extends Controller
{
    use CacheAware;

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
            $cacheKey = "regions:index:{$locale}:" . ($countryFilter ? "country:{$countryFilter}" : "all");

            $regions = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($countryFilter) {
                $query = Region::with(['country.translations', 'translations'])
                    ->withBeaches()
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }]);

                // Filter by country if provided
                if ($countryFilter) {
                    $query->byCountry($countryFilter);
                }

                return $query->get([
                    'id',
                    'code',
                    'country_code',
                    'status_id'
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => RegionResource::collection($regions),
                'message' => 'Regions retrieved successfully'
            ], 200);

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
     *                 ref="#/components/schemas/Region"
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

            $region = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale, $countryCode) {
                $query = Region::with([
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
                        $q->where('slug', $slug)->where('locale', $locale);
                    });

                // Filter by country if provided
                if ($countryCode) {
                    $query->byCountry($countryCode);
                }

                return $query->first([
                    'id',
                    'code',
                    'country_code',
                    'status_id'
                ]);
            });

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new RegionResource($region),
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
}

