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
use App\Traits\ProvidesWeather;

/**
 * @OA\Tag(
 *     name="Countries",
 *     description="API Endpoints for countries"
 * )
 */
class CountryController extends Controller
{
    use CacheAware, ProvidesWeather;

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
     *                     @OA\Property(property="image_path", type="string", nullable=true, example="/images/countries/slovenia.jpg"),
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
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }]);

                // Filter by continent if provided
                if ($continentFilter) {
                    $query->byContinent($continentFilter);
                }

                return $query->get([
                    'code',
                    'iso3',
                    'continent_code',
                    'image_path'
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
     *                 @OA\Property(property="image_path", type="string", nullable=true, example="/images/countries/slovenia.jpg"),
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
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->where('code', $code)
                    ->first([
                        'code',
                        'iso3',
                        'continent_code',
                        'image_path'
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

    /**
     * @OA\Get(
     *     path="/api/v1/countries/{code}/weather",
     *     operationId="getCountryWeather",
     *     summary="Get current weather for a country",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific country (typically capital or centroid coordinates). Weather data is cached for 1 hour.",
     *     tags={"Countries"},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Country code (e.g., HR, SI, IT)",
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
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="country_slug", type="string", example="croatia"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="string", example="45.1000"),
     *                     @OA\Property(property="longitude", type="string", example="15.2000")
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
     *                     @OA\Property(property="location_name", type="string", example="Croatia")
     *                 ),
     *                 @OA\Property(property="sea_temperature", type="number", format="float", example=23.5, nullable=true),
     *                 @OA\Property(property="cached_at", type="string", format="date-time", example="2025-11-29T10:17:50+00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Country not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Country has no coordinates",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Country has no coordinates for weather data")
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
    public function weather(string $code): JsonResponse
    {
        try {
            // Find country by code
            $country = Country::with('translations')
                ->where('code', strtoupper($code))
                ->first(['id', 'code', 'latitude', 'longitude']);

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            // Get country slug in current locale
            $countrySlug = $this->getCountrySlug($country);

            // Use ProvidesWeather trait to build response
            return $this->buildWeatherResponse($country, 'country', $countrySlug);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving weather data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get country slug in current locale
     */
    private function getCountrySlug($country): ?string
    {
        $locale = app()->getLocale();

        $translation = $country->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $country->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }
}
