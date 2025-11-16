<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

/**
 * @OA\Tag(
 *     name="Cities",
 *     description="API Endpoints for cities"
 * )
 */
class CityController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/cities",
     *     operationId="getCities",
     *     tags={"Cities"},
     *     summary="Get all cities that have beaches",
     *     description="Returns a list of cities that have at least one beach. Can be filtered by country. Returns minimal data: id, code, name, slug, country_code, region_id, beaches_count, and country object (without continent, badges, articles).",
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
            $countryFilter = $request->get('country_code');
            $cacheKey = "cities:index:{$locale}:" . ($countryFilter ? "country:{$countryFilter}" : "all");

            $cities = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($countryFilter) {
                $query = City::with(['country.translations', 'translations'])
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
                    'region_id',
                    'status_id'
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => CityResource::collection($cities),
                'message' => 'Cities retrieved successfully'
            ], 200);

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
     *     description="Returns a specific city by slug with full details including country, region, badges, and articles. Can be filtered by country code.",
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
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/City"
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
            $countryCode = $request->get('country_code');
            $cacheKey = "city:show:{$locale}:{$slug}" . ($countryCode ? ":{$countryCode}" : "");

            $city = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale, $countryCode) {
                $query = City::with([
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
                    'region_id',
                    'status_id'
                ]);
            });

            if (!$city) {
                return response()->json([
                    'success' => false,
                    'message' => 'City not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new CityResource($city),
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
}

