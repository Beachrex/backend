<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

/**
 * @OA\Tag(
 *     name="Countries",
 *     description="API Endpoints for countries"
 * )
 */
class CountryController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/countries",
     *     operationId="getCountries",
     *     tags={"Countries"},
     *     summary="Get all countries that have beaches",
     *     description="Returns a list of countries that have at least one beach. Can be filtered by continent.",
     *     @OA\Parameter(
     *         name="continent",
     *         in="query",
     *         description="Filter by continent code (e.g., EU, AS, NA)",
     *         required=false,
     *         @OA\Schema(type="string", example="EU")
     *     ),
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
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="SI"),
     *                     @OA\Property(property="name", type="string", example="Slovenia"),
     *                     @OA\Property(property="full_name", type="string", example="Republic of Slovenia"),
     *                     @OA\Property(property="iso3", type="string", example="SVN"),
     *                     @OA\Property(property="continent_code", type="string", example="EU"),
     *                     @OA\Property(property="beaches_count", type="integer", example=5)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Countries retrieved successfully"
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
            $continentFilter = $request->get('continent');
            $cacheKey = "countries:index:{$locale}:" . ($continentFilter ? "continent:{$continentFilter}" : "all");

            $countries = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($continentFilter) {
                $query = Country::with(['continent.translations', 'translations'])
                    ->withBeaches()
                    ->withCount('beaches');

                // Filter by continent if provided
                if ($continentFilter) {
                    $query->byContinent($continentFilter);
                }

                return $query->get([
                    'code',
                    'iso3',
                    'continent_code'
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => CountryResource::collection($countries),
                'message' => 'Countries retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving countries',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/countries/{code}",
     *     operationId="getCountry",
     *     tags={"Countries"},
     *     summary="Get a specific country",
     *     description="Returns a specific country by code with its beaches count and continent information",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Country code (e.g., SI, US, DE)",
     *         required=true,
     *         @OA\Schema(type="string", example="SI")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="SI"),
     *                 @OA\Property(property="name", type="string", example="Slovenia"),
     *                 @OA\Property(property="full_name", type="string", example="Republic of Slovenia"),
     *                 @OA\Property(property="iso3", type="string", example="SVN"),
     *                 @OA\Property(property="continent_code", type="string", example="EU"),
     *                 @OA\Property(property="beaches_count", type="integer", example=5),
     *                 @OA\Property(
     *                     property="continent",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="EU"),
     *                     @OA\Property(property="name", type="string", example="Europe")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Country retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Country not found")
     *         )
     *     )
     * )
     */
    public function show(string $code): JsonResponse
    {
        try {
            $code = strtoupper($code);
            $locale = app()->getLocale();
            $cacheKey = "country:show:{$locale}:{$code}";

            $country = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($code) {
                return Country::with([
                        'continent.translations',
                        'translations',
                        'badges.translations',
                        'badges.status',
                        'articles.translations',
                        'articles.status'
                    ])
                    ->withCount('beaches')
                    ->where('code', $code)
                    ->first([
                        'code',
                        'iso3',
                        'continent_code'
                    ]);
            });

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new CountryResource($country),
                'message' => 'Country retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the country',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
