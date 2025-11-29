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
use App\Traits\ProvidesWeather;

/**
 * @OA\Tag(
 *     name="Cities",
 *     description="API Endpoints for cities"
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
                        // Try requested locale first, fallback to English
                        $q->where('slug', $slug)
                          ->whereIn('locale', [$locale, 'en']);
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

    /**
     * @OA\Get(
     *     path="/api/v1/cities/{slug}/weather",
     *     operationId="getCityWeather",
     *     summary="Get current weather for a city",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific city. Weather data is cached for 1 hour.",
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
            $countryCode = $request->get('country_code');

            // Find city by slug
            $query = City::with('translations')
                ->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)
                      ->whereIn('locale', [$locale, 'en']);
                });

            // Filter by country if provided
            if ($countryCode) {
                $query->byCountry($countryCode);
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
}

