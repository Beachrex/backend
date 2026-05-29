<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Beach;
use App\Models\City;
use App\Models\Country;
use App\Models\Island;
use App\Models\Region;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MeiliSearch\Client;

/**
 * @OA\Tag(
 *     name="Search",
 *     description="Global search API endpoints for searching across beaches, cities, islands, regions, and countries"
 * )
 */
class SearchController extends Controller
{
    use CacheAware;

    protected Client $meilisearch;

    public function __construct()
    {
        $this->meilisearch = new Client(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search",
     *     summary="Global search across all entities",
     *     description="Search across beaches, cities, islands, regions, and countries with grouped results. Returns localized slugs based on Accept-Language header for URL building. Results are grouped by entity type with highlighting and typo tolerance.",
     *     operationId="globalSearch",
     *     tags={"Search"},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Locale for localized slugs (e.g., en, si, de, hr). Defaults to 'en' if not provided or translation not available.",
     *         required=false,
     *         @OA\Schema(type="string", example="en")
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query string",
     *         required=true,
     *         @OA\Schema(type="string", example="miami")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of results per entity type (default: 5, max: 20)",
     *         required=false,
     *         @OA\Schema(type="integer", example=5, minimum=1, maximum=20)
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Filter results by country code (e.g., US, HR)",
     *         required=false,
     *         @OA\Schema(type="string", example="US")
     *     ),
     *     @OA\Parameter(
     *         name="types",
     *         in="query",
     *         description="Comma-separated list of entity types to search (beach,city,island,region,country). If not provided, searches all types.",
     *         required=false,
     *         @OA\Schema(type="string", example="beach,city")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results grouped by entity type",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="beaches",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=123),
     *                         @OA\Property(property="name", type="string", example="Miami Beach"),
     *                         @OA\Property(property="slug", type="string", example="miami-beach", description="Localized beach slug"),
     *                         @OA\Property(property="country_code", type="string", example="US"),
     *                         @OA\Property(property="country_name", type="string", example="United States"),
     *                         @OA\Property(property="region_name", type="string", example="Florida"),
     *                         @OA\Property(property="city_name", type="string", example="Miami"),
     *                         @OA\Property(property="island_name", type="string", example=""),
     *                         @OA\Property(property="main_image", type="string", example="miami-beach.jpg"),
     *                         @OA\Property(property="type", type="string", example="beach"),
     *                         @OA\Property(property="continent_slug", type="string", example="north-america", description="Localized continent slug for URL building"),
     *                         @OA\Property(property="country_slug", type="string", example="united-states", description="Localized country slug for URL building"),
     *                         @OA\Property(property="region_slug", type="string", example="florida", description="Localized region slug for URL building (nullable)"),
     *                         @OA\Property(property="city_slug", type="string", example="miami", description="Localized city slug for URL building (nullable)"),
     *                         @OA\Property(property="island_slug", type="string", example=null, description="Localized island slug for URL building (nullable)")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="cities",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=45),
     *                         @OA\Property(property="code", type="string", example="miami"),
     *                         @OA\Property(property="name", type="string", example="Miami"),
     *                         @OA\Property(property="slug", type="string", example="miami", description="Localized city slug"),
     *                         @OA\Property(property="country_code", type="string", example="US"),
     *                         @OA\Property(property="country_name", type="string", example="United States"),
     *                         @OA\Property(property="region_name", type="string", example="Florida"),
     *                         @OA\Property(property="island_name", type="string", example=""),
     *                         @OA\Property(property="main_image", type="string", example="miami.jpg"),
     *                         @OA\Property(property="type", type="string", example="city"),
     *                         @OA\Property(property="continent_slug", type="string", example="north-america", description="Localized continent slug for URL building"),
     *                         @OA\Property(property="country_slug", type="string", example="united-states", description="Localized country slug for URL building"),
     *                         @OA\Property(property="region_slug", type="string", example="florida", description="Localized region slug for URL building (nullable)"),
     *                         @OA\Property(property="island_slug", type="string", example=null, description="Localized island slug for URL building (nullable)")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="islands",
     *                     type="array",
     *                     description="Island results with localized slugs for URL building",
     *                     @OA\Items(
     *                         @OA\Property(property="continent_slug", type="string"),
     *                         @OA\Property(property="country_slug", type="string"),
     *                         @OA\Property(property="region_slug", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="regions",
     *                     type="array",
     *                     description="Region results with localized slugs for URL building",
     *                     @OA\Items(
     *                         @OA\Property(property="continent_slug", type="string"),
     *                         @OA\Property(property="country_slug", type="string")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="countries",
     *                     type="array",
     *                     description="Country results with localized slugs for URL building",
     *                     @OA\Items(
     *                         @OA\Property(property="continent_slug", type="string")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="query", type="string", example="miami"),
     *                 @OA\Property(property="total_results", type="integer", example=12),
     *                 @OA\Property(
     *                     property="results_by_type",
     *                     type="object",
     *                     @OA\Property(property="beaches", type="integer", example=5),
     *                     @OA\Property(property="cities", type="integer", example=2),
     *                     @OA\Property(property="islands", type="integer", example=0),
     *                     @OA\Property(property="regions", type="integer", example=3),
     *                     @OA\Property(property="countries", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Search completed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - missing or invalid query parameter"
     *     )
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:255',
            'limit' => 'nullable|integer|min:1|max:20',
            'country_code' => 'nullable|string|size:2',
            'types' => 'nullable|string',
        ]);

        $query = $validated['q'];
        $limit = (int) ($validated['limit'] ?? 5);
        $countryCode = isset($validated['country_code']) ? strtoupper($validated['country_code']) : null;
        $types = isset($validated['types']) ? explode(',', $validated['types']) : ['beach', 'city', 'island', 'region', 'country'];

        // Get locale from Accept-Language header, fallback to app locale
        // Parse Accept-Language header to get the primary language code (e.g., "si" from "si-SI,si;q=0.9,en;q=0.8")
        $acceptLanguage = $request->header('Accept-Language', app()->getLocale());

        // Extract the primary language code (2-letter ISO code)
        // Handle formats: "si", "si-SI", "si,en;q=0.9", "si-SI,en-US;q=0.9"
        $localeParts = explode(',', $acceptLanguage);  // Split by comma to get first language
        $primaryLanguage = trim($localeParts[0]);       // Get first language (e.g., "si-SI" or "si")
        $locale = strtolower(explode('-', $primaryLanguage)[0]); // Extract just "si" from "si-SI"

        // Log search request for debugging
        \Log::info('Search request', [
            'query' => $query,
            'locale' => $locale,
            'Accept-Language header' => $acceptLanguage,
            'parsed locale' => $locale,
        ]);

        // Create cache key based on search parameters including locale
        $cacheKey = 'search:' . md5($query . $limit . ($countryCode ?? '') . implode(',', $types) . $locale);

        // Check if cache should be bypassed (for debugging/testing)
        // Use ?_nocache=1 query parameter to bypass cache in development
        $bypassCache = $request->has('_nocache');
        $cacheTtl = $bypassCache ? 0 : CacheService::SHORT_TTL;

        $results = $this->cacheRemember($cacheKey, $cacheTtl, function () use ($query, $limit, $countryCode, $types, $locale) {
            $searchResults = [
                'beaches' => [],
                'cities' => [],
                'islands' => [],
                'regions' => [],
                'countries' => [],
            ];

            // Search beaches
            if (in_array('beach', $types)) {
                $beachResults = Beach::search($query)
                    ->when($countryCode, function ($search) use ($countryCode) {
                        return $search->where('country_code', $countryCode);
                    })
                    ->take($limit)
                    ->get()
                    ->load([
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

                $searchResults['beaches'] = $beachResults->map(function ($beach) use ($locale) {
                    // Get name and slug in current locale, fallback to English
                    $translation = $beach->translations->firstWhere('locale', $locale);
                    $translationEn = $beach->translations->firstWhere('locale', 'en');

                    $name = $translation?->name ?? $translationEn?->name ?? null;
                    $slug = $translation?->slug ?? $translationEn?->slug ?? null;

                    // Get primary image from beach gallery (collection_id = 1)
                    $mainImage = null;
                    if ($beach->media && $beach->media->isNotEmpty()) {
                        $primaryMedia = $beach->media->first();
                        if ($primaryMedia && $primaryMedia->file_path) {
                            $mainImage = $primaryMedia->file_path;
                        }
                    }

                    // Get all location slugs and localized names for URL building
                    $continentTranslation = $beach->continent?->translations->firstWhere('locale', $locale);
                    $continentSlug = $continentTranslation?->slug ?? $beach->continent?->translations->firstWhere('locale', 'en')?->slug;

                    $countryTranslation = $beach->country?->translations->firstWhere('locale', $locale);
                    $countrySlug = $countryTranslation?->slug ?? $beach->country?->translations->firstWhere('locale', 'en')?->slug;
                    $countryName = $countryTranslation?->name ?? $beach->country?->translations->firstWhere('locale', 'en')?->name ?? $beach->country?->name ?? '';

                    $regionTranslation = $beach->region?->translations->firstWhere('locale', $locale);
                    $regionSlug = $regionTranslation?->slug ?? $beach->region?->translations->firstWhere('locale', 'en')?->slug;
                    $regionName = $regionTranslation?->name ?? $beach->region?->translations->firstWhere('locale', 'en')?->name ?? $beach->region?->name ?? '';

                    $cityTranslation = $beach->city?->translations->firstWhere('locale', $locale);
                    $citySlug = $cityTranslation?->slug ?? $beach->city?->translations->firstWhere('locale', 'en')?->slug;
                    $cityName = $cityTranslation?->name ?? $beach->city?->translations->firstWhere('locale', 'en')?->name ?? $beach->city?->name ?? '';

                    $islandTranslation = $beach->island?->translations->firstWhere('locale', $locale);
                    $islandSlug = $islandTranslation?->slug ?? $beach->island?->translations->firstWhere('locale', 'en')?->slug;
                    $islandName = $islandTranslation?->name ?? $beach->island?->translations->firstWhere('locale', 'en')?->name ?? $beach->island?->name ?? '';

                    return [
                        'id' => $beach->id,
                        'name' => $name,
                        'slug' => $slug,
                        'country_code' => $beach->country_code,
                        'country_name' => $countryName,
                        'region_name' => $regionName,
                        'city_name' => $cityName,
                        'island_name' => $islandName,
                        'main_image' => $mainImage,
                        'type' => 'beach',
                        // URL building slugs
                        'continent_slug' => $continentSlug,
                        'country_slug' => $countrySlug,
                        'region_slug' => $regionSlug,
                        'city_slug' => $citySlug,
                        'island_slug' => $islandSlug,
                    ];
                })->toArray();
            }

            // Search cities
            if (in_array('city', $types)) {
                $cityResults = City::search($query)
                    ->when($countryCode, function ($search) use ($countryCode) {
                        return $search->where('country_code', $countryCode);
                    })
                    ->take($limit)
                    ->get()
                    ->load([
                        'translations',
                        'country.translations',
                        'country.continent.translations',
                        'region.translations',
                        'island.translations'
                    ]);

                $searchResults['cities'] = $cityResults->map(function ($city) use ($locale) {
                    // Get name and slug in current locale, fallback to English
                    $translation = $city->translations->firstWhere('locale', $locale);
                    $cityName = $translation?->name ?? $city->translations->firstWhere('locale', 'en')?->name ?? $city->name;
                    $slug = $translation?->slug ?? $city->translations->firstWhere('locale', 'en')?->slug ?? $city->code;

                    // Get location slugs and localized names for URL building
                    $continentTranslation = $city->country?->continent?->translations->firstWhere('locale', $locale);
                    $continentSlug = $continentTranslation?->slug ?? $city->country?->continent?->translations->firstWhere('locale', 'en')?->slug;

                    $countryTranslation = $city->country?->translations->firstWhere('locale', $locale);
                    $countrySlug = $countryTranslation?->slug ?? $city->country?->translations->firstWhere('locale', 'en')?->slug;
                    $countryName = $countryTranslation?->name ?? $city->country?->translations->firstWhere('locale', 'en')?->name ?? $city->country?->name ?? '';

                    $regionTranslation = $city->region?->translations->firstWhere('locale', $locale);
                    $regionSlug = $regionTranslation?->slug ?? $city->region?->translations->firstWhere('locale', 'en')?->slug;
                    $regionName = $regionTranslation?->name ?? $city->region?->translations->firstWhere('locale', 'en')?->name ?? $city->region?->name ?? '';

                    $islandTranslation = $city->island?->translations->firstWhere('locale', $locale);
                    $islandSlug = $islandTranslation?->slug ?? $city->island?->translations->firstWhere('locale', 'en')?->slug;
                    $islandName = $islandTranslation?->name ?? $city->island?->translations->firstWhere('locale', 'en')?->name ?? $city->island?->name ?? '';

                    return [
                        'id' => $city->id,
                        'code' => $city->code,
                        'name' => $cityName,
                        'slug' => $slug,
                        'country_code' => $city->country_code,
                        'country_name' => $countryName,
                        'region_name' => $regionName,
                        'island_name' => $islandName,
                        'main_image' => $city->main_image,
                        'type' => 'city',
                        // URL building slugs
                        'continent_slug' => $continentSlug,
                        'country_slug' => $countrySlug,
                        'region_slug' => $regionSlug,
                        'island_slug' => $islandSlug,
                    ];
                })->toArray();
            }

            // Search islands
            if (in_array('island', $types)) {
                $islandResults = Island::search($query)
                    ->when($countryCode, function ($search) use ($countryCode) {
                        return $search->where('country_code', $countryCode);
                    })
                    ->take($limit)
                    ->get()
                    ->load([
                        'translations',
                        'country.translations',
                        'country.continent.translations',
                        'region.translations'
                    ]);

                $searchResults['islands'] = $islandResults->map(function ($island) use ($locale) {
                    // Get name and slug in current locale, fallback to English
                    $translation = $island->translations->firstWhere('locale', $locale);
                    $islandName = $translation?->name ?? $island->translations->firstWhere('locale', 'en')?->name ?? $island->name;
                    $slug = $translation?->slug ?? $island->translations->firstWhere('locale', 'en')?->slug ?? $island->code;

                    // Get location slugs and localized names for URL building
                    $continentTranslation = $island->country?->continent?->translations->firstWhere('locale', $locale);
                    $continentSlug = $continentTranslation?->slug ?? $island->country?->continent?->translations->firstWhere('locale', 'en')?->slug;

                    $countryTranslation = $island->country?->translations->firstWhere('locale', $locale);
                    $countrySlug = $countryTranslation?->slug ?? $island->country?->translations->firstWhere('locale', 'en')?->slug;
                    $countryName = $countryTranslation?->name ?? $island->country?->translations->firstWhere('locale', 'en')?->name ?? $island->country?->name ?? '';

                    $regionTranslation = $island->region?->translations->firstWhere('locale', $locale);
                    $regionSlug = $regionTranslation?->slug ?? $island->region?->translations->firstWhere('locale', 'en')?->slug;
                    $regionName = $regionTranslation?->name ?? $island->region?->translations->firstWhere('locale', 'en')?->name ?? $island->region?->name ?? '';

                    return [
                        'id' => $island->id,
                        'code' => $island->code,
                        'name' => $islandName,
                        'slug' => $slug,
                        'country_code' => $island->country_code,
                        'country_name' => $countryName,
                        'region_name' => $regionName,
                        'main_image' => $island->main_image,
                        'type' => 'island',
                        // URL building slugs
                        'continent_slug' => $continentSlug,
                        'country_slug' => $countrySlug,
                        'region_slug' => $regionSlug,
                    ];
                })->toArray();
            }

            // Search regions
            if (in_array('region', $types)) {
                $regionResults = Region::search($query)
                    ->when($countryCode, function ($search) use ($countryCode) {
                        return $search->where('country_code', $countryCode);
                    })
                    ->take($limit)
                    ->get()
                    ->load([
                        'translations',
                        'country.translations',
                        'country.continent.translations'
                    ]);

                $searchResults['regions'] = $regionResults->map(function ($region) use ($locale) {
                    // Get name and slug in current locale, fallback to English
                    $translation = $region->translations->firstWhere('locale', $locale);
                    $regionName = $translation?->name ?? $region->translations->firstWhere('locale', 'en')?->name ?? $region->name;
                    $slug = $translation?->slug ?? $region->translations->firstWhere('locale', 'en')?->slug ?? $region->code;

                    // Get location slugs and localized names for URL building
                    $continentTranslation = $region->country?->continent?->translations->firstWhere('locale', $locale);
                    $continentSlug = $continentTranslation?->slug ?? $region->country?->continent?->translations->firstWhere('locale', 'en')?->slug;

                    $countryTranslation = $region->country?->translations->firstWhere('locale', $locale);
                    $countrySlug = $countryTranslation?->slug ?? $region->country?->translations->firstWhere('locale', 'en')?->slug;
                    $countryName = $countryTranslation?->name ?? $region->country?->translations->firstWhere('locale', 'en')?->name ?? $region->country?->name ?? '';

                    return [
                        'id' => $region->id,
                        'code' => $region->code,
                        'name' => $regionName,
                        'slug' => $slug,
                        'country_code' => $region->country_code,
                        'country_name' => $countryName,
                        'main_image' => $region->main_image,
                        'type' => 'region',
                        // URL building slugs
                        'continent_slug' => $continentSlug,
                        'country_slug' => $countrySlug,
                    ];
                })->toArray();
            }

            // Search countries
            if (in_array('country', $types)) {
                // Get IDs from search, then query with eager loading
                // Preserve Meilisearch relevance order using FIELD() in ORDER BY
                $searchIds = Country::search($query)
                    ->take($limit)
                    ->keys()
                    ->toArray();

                if (!empty($searchIds)) {
                    $countryResults = Country::with(['translations', 'continent.translations'])
                        ->whereIn('id', $searchIds)
                        ->orderByRaw('FIELD(id, ' . implode(',', $searchIds) . ')')
                        ->get();
                } else {
                    $countryResults = collect();
                }

                $searchResults['countries'] = $countryResults->map(function ($country) use ($locale) {
                    // Get name and slug in current locale, fallback to English
                    $translation = $country->translations->firstWhere('locale', $locale);
                    $countryName = $translation?->name ?? $country->translations->firstWhere('locale', 'en')?->name ?? $country->name;
                    $slug = $translation?->slug ?? $country->translations->firstWhere('locale', 'en')?->slug ?? $country->code;

                    // Get continent slug and localized name for URL building
                    $continentTranslation = $country->continent?->translations->firstWhere('locale', $locale);
                    $continentSlug = $continentTranslation?->slug ?? $country->continent?->translations->firstWhere('locale', 'en')?->slug;
                    $continentName = $continentTranslation?->name ?? $country->continent?->translations->firstWhere('locale', 'en')?->name ?? $country->continent?->name ?? '';

                    $result = [
                        'id' => $country->id,
                        'code' => $country->code,
                        'iso3' => $country->iso3,
                        'name' => $countryName,
                        'slug' => $slug,
                        'continent_code' => $country->continent_code,
                        'continent_name' => $continentName,
                        'main_image' => $country->image_path,
                        'type' => 'country',
                        // URL building slugs
                        'continent_slug' => $continentSlug,
                        // DEBUG
                        '_debug' => [
                            'translations_loaded' => $country->relationLoaded('translations'),
                            'translations_count' => $country->translations->count(),
                            'available_locales' => $country->translations->pluck('locale')->toArray(),
                            'requested_locale' => $locale,
                            'translation_found' => $translation !== null,
                            'translation_name' => $translation?->name,
                            'translation_slug' => $translation?->slug,
                        ],
                    ];

                    return $result;
                })->toArray();
            }

            return $searchResults;
        });

        // Calculate totals
        $totalResults = array_sum(array_map('count', $results));
        $resultsByType = array_map('count', $results);

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'query' => $query,
                'total_results' => $totalResults,
                'results_by_type' => $resultsByType,
            ],
            'message' => 'Search completed successfully',
        ], 200);
    }
}

