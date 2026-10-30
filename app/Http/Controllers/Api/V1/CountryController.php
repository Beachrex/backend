<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
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
     *                     @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg"),
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
            $continentFilter = $request->get('continent');
            $continentFilter = is_string($continentFilter) && $continentFilter !== '' ? $continentFilter : null;

            // Whole response cached per locale (ApiResponseCache: JSON, not models; cleared on content changes).
            return \App\Support\ApiResponseCache::json('countries.index', ['continent' => $continentFilter], \App\Support\ApiResponseCache::TTL_REFERENCE, function () use ($continentFilter) {
                $query = Country::with(['continent.translations', 'translations'])
                    ->withBeaches()
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }]);

                // Filter by continent if provided
                if ($continentFilter) {
                    $query->byContinent($continentFilter);
                }

                $countries = $query->get([
                    'code',
                    'iso3',
                    'continent_code',
                    'image_path'
                ]);

                return [
                    'success' => true,
                    'data' => CountryResource::collection($countries),
                    'message' => 'Countries retrieved successfully'
                ];
            });

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
     *     path="/api/v1/countries/{codeOrSlug}",
     *     operationId="getCountry",
     *     tags={"Countries"},
     *     summary="Get a specific country",
     *     description="Returns a specific country by code or slug with its beaches count and continent information.",
     *     @OA\Parameter(
     *         name="codeOrSlug",
     *         in="path",
     *         description="Country code (e.g., SI, US, DE) or localized slug (e.g., italy, slovenia)",
     *         required=true,
     *         @OA\Schema(type="string", example="SI")
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
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="SI"),
     *                 @OA\Property(property="name", type="string", example="Slovenia"),
     *                 @OA\Property(property="full_name", type="string", example="Republic of Slovenia"),
     *                 @OA\Property(property="slug", type="string", example="slovenia"),
     *                 @OA\Property(property="iso3", type="string", example="SVN"),
     *                 @OA\Property(property="continent_code", type="string", example="EU"),
     *                 @OA\Property(property="latitude", type="number", format="double", nullable=true, example=46.1512),
     *                 @OA\Property(property="longitude", type="number", format="double", nullable=true, example=14.9955),
     *                 @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg"),
     *                 @OA\Property(property="beaches_count", type="integer", example=5),
     *                 @OA\Property(
     *                     property="continent",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="EU"),
     *                     @OA\Property(property="name", type="string", example="Europe")
     *                 ),
     *                 @OA\Property(
     *                     property="navigation",
     *                     type="object",
     *                     @OA\Property(
     *                         property="previous",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="code", type="string", example="HR"),
     *                         @OA\Property(property="name", type="string", example="Croatia"),
     *                         @OA\Property(property="slug", type="string", example="croatia"),
     *                         @OA\Property(property="latitude", type="string", example="45.1"),
     *                         @OA\Property(property="longitude", type="string", example="15.2"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="/storage/countries/croatia.jpg"),
     *                         @OA\Property(
     *                             property="location",
     *                             type="object",
     *                             @OA\Property(property="continent_slug", type="string", example="europe")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="next",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="code", type="string", example="IT"),
     *                         @OA\Property(property="name", type="string", example="Italy"),
     *                         @OA\Property(property="slug", type="string", example="italy"),
     *                         @OA\Property(property="latitude", type="string", example="41.8719"),
     *                         @OA\Property(property="longitude", type="string", example="12.5674"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="/storage/countries/italy.jpg"),
     *                         @OA\Property(
     *                             property="location",
     *                             type="object",
     *                             @OA\Property(property="continent_slug", type="string", example="europe")
     *                         )
     *                     )
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
    public function show(string $codeOrSlug, Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();

            // Detect whether input looks like a country code (2-3 letters) or a slug
            $isCode = preg_match('/^[A-Za-z]{2,3}$/', $codeOrSlug) === 1;

            // Normalise cache key to avoid collisions between code/slug variants
            $identifierType = $isCode ? 'code' : 'slug';
            $normalizedIdentifier = $isCode ? strtoupper($codeOrSlug) : strtolower($codeOrSlug);
            $cacheKey = "country:show:{$locale}:{$identifierType}:{$normalizedIdentifier}";

            $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($isCode, $codeOrSlug, $locale, $request) {
                $query = Country::with([
                        'continent.translations',
                        'translations',
                        'badges.translations',
                        'badges.status',
                        'articles.translations',
                        'articles.status'
                    ])
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }]);

                if ($isCode) {
                    // Lookup by country code (case-insensitive)
                    $code = strtoupper($codeOrSlug);
                    $query->where('code', $code);
                } else {
                    // Lookup by localized slug from translations, try requested locale first then English
                    $slug = strtolower($codeOrSlug);
                    $query->whereHas('translations', function ($q) use ($slug, $locale) {
                        $q->where('slug', $slug)
                          ->whereIn('locale', [$locale, 'en']);
                    });
                }

                $country = $query->first([
                    'code',
                    'iso3',
                    'continent_code',
                    'image_path'
                ]);

                if (!$country) {
                    return null;
                }

                // Get next and previous countries
                $navigation = $this->getNavigationCountries($country, $locale);

                // Parent levels' published beach counts for the location pill tabs.
                PublishedBeachCounts::loadOnParents($country, ['continent']);

                $resource = new CountryResource($country);
                $resourceArray = $resource->toArray($request);

                // Add navigation to response
                $resourceArray['navigation'] = $navigation;

                return $resourceArray;
            });

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
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
     *     path="/api/v1/countries/{codeOrSlug}/weather",
     *     operationId="getCountryWeather",
     *     summary="Get current weather for a country",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific country (typically capital or centroid coordinates). Weather data is cached for 1 hour.",
     *     tags={"Countries"},
     *     @OA\Parameter(
     *         name="codeOrSlug",
     *         in="path",
     *         required=true,
     *         description="Country code (e.g., HR, SI, IT) or localized slug (e.g., italy, slovenia)",
     *         @OA\Schema(type="string", example="italy")
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
    public function weather(string $codeOrSlug): JsonResponse
    {
        try {
            $locale = app()->getLocale();

            // Detect whether input looks like a country code (2-3 letters) or a slug
            $isCode = preg_match('/^[A-Za-z]{2,3}$/', $codeOrSlug) === 1;

            $query = Country::with('translations');

            if ($isCode) {
                $query->where('code', strtoupper($codeOrSlug));
            } else {
                $slug = strtolower($codeOrSlug);
                $query->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)
                      ->whereIn('locale', [$locale, 'en']);
                });
            }

            $country = $query->first(['id', 'code', 'latitude', 'longitude']);

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

    /**
     * Get next and previous countries based on continent and code order
     * Previous = highest code < current code in same continent
     * Next = lowest code > current code in same continent
     */
    private function getNavigationCountries(Country $currentCountry, string $locale): array
    {
        // Get previous country: highest code < current code in same continent
        // Only include countries that have beaches with status_id = 1
        $previousCountry = DB::select("
            SELECT
                c.code,
                c.iso3,
                c.continent_code,
                c.latitude,
                c.longitude,
                c.image_path as main_image,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug,
                COALESCE(continent_t.slug, continent_t_en.slug) as continent_slug
            FROM countries c
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN country_translation ct_en ON c.code = ct_en.country_code AND ct_en.locale = 'en'
            LEFT JOIN continent_translation continent_t ON c.continent_code = continent_t.continent_code AND continent_t.locale = ?
            LEFT JOIN continent_translation continent_t_en ON c.continent_code = continent_t_en.continent_code AND continent_t_en.locale = 'en'
            WHERE c.continent_code = ?
              AND c.code < ?
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.country_code = c.code
                    AND b.status_id = 1
              )
            ORDER BY c.code DESC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $currentCountry->continent_code,
            $currentCountry->code,
        ]);

        // Get next country: lowest code > current code in same continent
        // Only include countries that have beaches with status_id = 1
        $nextCountry = DB::select("
            SELECT
                c.code,
                c.iso3,
                c.continent_code,
                c.latitude,
                c.longitude,
                c.image_path as main_image,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug,
                COALESCE(continent_t.slug, continent_t_en.slug) as continent_slug
            FROM countries c
            LEFT JOIN country_translation ct ON c.code = ct.country_code AND ct.locale = ?
            LEFT JOIN country_translation ct_en ON c.code = ct_en.country_code AND ct_en.locale = 'en'
            LEFT JOIN continent_translation continent_t ON c.continent_code = continent_t.continent_code AND continent_t.locale = ?
            LEFT JOIN continent_translation continent_t_en ON c.continent_code = continent_t_en.continent_code AND continent_t_en.locale = 'en'
            WHERE c.continent_code = ?
              AND c.code > ?
              AND EXISTS (
                  SELECT 1 FROM beach b
                  WHERE b.country_code = c.code
                    AND b.status_id = 1
              )
            ORDER BY c.code ASC
            LIMIT 1
        ", [
            $locale,
            $locale,
            $currentCountry->continent_code,
            $currentCountry->code,
        ]);

        // Format the results
        $previous = isset($previousCountry[0]) ? [
            'code' => $previousCountry[0]->code,
            'name' => $previousCountry[0]->name,
            'slug' => $previousCountry[0]->slug,
            'latitude' => $previousCountry[0]->latitude,
            'longitude' => $previousCountry[0]->longitude,
            'main_image' => $previousCountry[0]->main_image,
            'location' => [
                'continent_slug' => $previousCountry[0]->continent_slug,
            ],
        ] : null;

        $next = isset($nextCountry[0]) ? [
            'code' => $nextCountry[0]->code,
            'name' => $nextCountry[0]->name,
            'slug' => $nextCountry[0]->slug,
            'latitude' => $nextCountry[0]->latitude,
            'longitude' => $nextCountry[0]->longitude,
            'main_image' => $nextCountry[0]->main_image,
            'location' => [
                'continent_slug' => $nextCountry[0]->continent_slug,
            ],
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }
}
