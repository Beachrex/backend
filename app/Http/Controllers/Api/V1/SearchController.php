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
use Illuminate\Support\Str;
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
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="brac"),
     *                         @OA\Property(property="name", type="string", example="Brač"),
     *                         @OA\Property(property="slug", type="string", example="brac", description="Localized island slug"),
     *                         @OA\Property(property="country_code", type="string", example="HR"),
     *                         @OA\Property(property="country_name", type="string", example="Croatia"),
     *                         @OA\Property(property="region_name", type="string", example="Dalmatia - Split"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="img/islands/brac.jpg"),
     *                         @OA\Property(property="type", type="string", example="island"),
     *                         @OA\Property(property="continent_slug", type="string", nullable=true),
     *                         @OA\Property(property="country_slug", type="string", nullable=true),
     *                         @OA\Property(property="region_slug", type="string", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="regions",
     *                     type="array",
     *                     description="Region results with localized slugs for URL building",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="dalmatia-split"),
     *                         @OA\Property(property="name", type="string", example="Dalmatia - Split"),
     *                         @OA\Property(property="slug", type="string", example="dalmatia-split", description="Localized region slug"),
     *                         @OA\Property(property="country_code", type="string", example="HR"),
     *                         @OA\Property(property="country_name", type="string", example="Croatia"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="img/regions/dalmacija.jpg"),
     *                         @OA\Property(property="type", type="string", example="region"),
     *                         @OA\Property(property="continent_slug", type="string", nullable=true),
     *                         @OA\Property(property="country_slug", type="string", nullable=true)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="countries",
     *                     type="array",
     *                     description="Country results with localized slugs for URL building",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=7),
     *                         @OA\Property(property="code", type="string", example="HR"),
     *                         @OA\Property(property="iso3", type="string", nullable=true, example="HRV"),
     *                         @OA\Property(property="name", type="string", example="Croatia"),
     *                         @OA\Property(property="slug", type="string", example="croatia", description="Localized country slug"),
     *                         @OA\Property(property="continent_code", type="string", example="EU"),
     *                         @OA\Property(property="continent_name", type="string", example="Europe"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="img/countries/croatia.jpg"),
     *                         @OA\Property(property="type", type="string", example="country"),
     *                         @OA\Property(property="continent_slug", type="string", nullable=true)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="query", type="string", example="miami"),
     *                 @OA\Property(property="total_results", type="integer", example=12, description="Number of items returned (at most `limit` per type)"),
     *                 @OA\Property(
     *                     property="results_by_type",
     *                     type="object",
     *                     @OA\Property(property="beaches", type="integer", example=5),
     *                     @OA\Property(property="cities", type="integer", example=2),
     *                     @OA\Property(property="islands", type="integer", example=0),
     *                     @OA\Property(property="regions", type="integer", example=3),
     *                     @OA\Property(property="countries", type="integer", example=2)
     *                 ),
     *                 @OA\Property(property="total_matches", type="integer", example=533, description="Sum of totals_by_type: every match, not capped by `limit`"),
     *                 @OA\Property(
     *                     property="totals_by_type",
     *                     type="object",
     *                     description="Total matches per type (not capped by `limit`). When `place` is set, the types below it count everything in that place as the place pages do: active beaches, and towns/islands/regions with at least one active beach (e.g. all beaches in France). Those types are then listed most viewed (beaches) or most beaches (places) first.",
     *                     @OA\Property(property="beaches", type="integer", example=412),
     *                     @OA\Property(property="cities", type="integer", example=87),
     *                     @OA\Property(property="islands", type="integer", example=26),
     *                     @OA\Property(property="regions", type="integer", example=7),
     *                     @OA\Property(property="countries", type="integer", example=1)
     *                 ),
     *                 @OA\Property(
     *                     property="place",
     *                     type="object",
     *                     nullable=true,
     *                     description="The place the query names exactly (any language, case and diacritics ignored), or null. Results of the types below it are limited to it.",
     *                     @OA\Property(property="type", type="string", enum={"country","region","island","city"}, example="country"),
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="code", type="string", nullable=true, example="FR", description="Map filter code (country ISO code, region/island/city code)"),
     *                     @OA\Property(property="name", type="string", example="France", description="Localized name")
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

        // Locale set by SetLocaleFromHeader (Accept-Language, si -> sl)
        $locale = app()->getLocale();

        // v2: the cached payload also holds the totals and the named place (ADR 2026-10-28, search counts)
        $cacheKey = 'search:v2:' . md5($query . $limit . ($countryCode ?? '') . implode(',', $types) . $locale);

        // ?_nocache=1 bypasses the cache for debugging, only locally: on a public server anyone could use it
        // to skip the cache on every request (audit 2026-10-09).
        $bypassCache = $request->has('_nocache') && app()->environment('local', 'testing');
        $cacheTtl = $bypassCache ? 0 : CacheService::SHORT_TTL;

        $payload = $this->cacheRemember($cacheKey, $cacheTtl, function () use ($query, $limit, $countryCode, $types, $locale) {
            return $this->runSearch($query, $limit, $countryCode, $types, $locale);
        });

        $results = $payload['data'];
        $totals = $payload['totals'];

        return response()->json([
            'success' => true,
            'data' => $results,
            'meta' => [
                'query' => $query,
                // Items returned (at most `limit` per type)
                'total_results' => array_sum(array_map('count', $results)),
                'results_by_type' => array_map('count', $results),
                // All matches per type, not capped by `limit` (owner 2026-10-28: "France" showed Beaches 10)
                'total_matches' => array_sum($totals),
                'totals_by_type' => $totals,
                'place' => $payload['place'],
            ],
            'message' => 'Search completed successfully',
        ], 200);
    }

    /**
     * Search order: countries, regions, islands, cities, beaches. When the query is the name of exactly one
     * place of the first type that has such a match (e.g. "France" -> the country France), the types below it
     * are limited to that place: "France" lists and counts the beaches, cities, islands and regions in France,
     * not the documents that merely contain the word (an Italian beach describing the church of San Francesco).
     * ADR 2026-10-28 "Search counts are real totals; a place name scopes the result".
     *
     * @param  list<string>  $types
     * @return array{data: array<string, list<array<string, mixed>>>, totals: array<string, int>, place: array<string, mixed>|null}
     */
    private function runSearch(string $query, int $limit, ?string $countryCode, array $types, string $locale): array
    {
        $groups = [
            'country' => ['countries', Country::class],
            'region' => ['regions', Region::class],
            'island' => ['islands', Island::class],
            'city' => ['cities', City::class],
            'beach' => ['beaches', Beach::class],
        ];

        $data = ['beaches' => [], 'cities' => [], 'islands' => [], 'regions' => [], 'countries' => []];
        $totals = ['beaches' => 0, 'cities' => 0, 'islands' => 0, 'regions' => 0, 'countries' => 0];
        $place = null;

        foreach ($groups as $type => [$key, $class]) {
            if (! in_array($type, $types, true)) {
                continue;
            }

            $wheres = [];
            if ($countryCode && $type !== 'country') {
                $wheres['country_code'] = $countryCode;
            }
            $scope = $place !== null ? self::scopeFilters($place, $type) : [];

            // In a named place the lower types are read from the database with the rules of the place pages
            // (active beaches; towns, islands, regions with at least one active beach), so "Toscana: 43 beaches,
            // 21 towns" in search is the same number as on the Toscana page (owner follow-up 2026-10-28).
            [$models, $total] = $scope === []
                ? $this->searchModels($class, $query, $wheres, $limit)
                : $this->placeModels($class, $wheres + $scope, $limit);
            $totals[$key] = $total;

            $models = match ($type) {
                'beach' => $models->load([
                    'translations',
                    'continent.translations',
                    'country.translations',
                    'region.translations',
                    'city.translations',
                    'island.translations',
                    'media' => function ($q) {
                        $q->where('media_collection_id', 1)->where('is_primary', true)->where('status_id', 1);
                    },
                ]),
                'city' => $models->load(['translations', 'country.translations', 'country.continent.translations', 'region.translations', 'island.translations']),
                'island' => $models->load(['translations', 'country.translations', 'country.continent.translations', 'region.translations']),
                'region' => $models->load(['translations', 'country.translations', 'country.continent.translations']),
                'country' => $models->load(['translations', 'continent.translations']),
            };

            $data[$key] = $models->map(fn ($model) => match ($type) {
                'beach' => $this->mapBeach($model, $locale),
                'city' => $this->mapCity($model, $locale),
                'island' => $this->mapIsland($model, $locale),
                'region' => $this->mapRegion($model, $locale),
                'country' => $this->mapCountry($model, $locale),
            })->values()->all();

            if ($place === null && $type !== 'beach') {
                $named = self::namedPlace($models, $query);
                if ($named !== null) {
                    $place = [
                        'type' => $type,
                        'id' => $named->id,
                        'code' => $type === 'country' ? $named->code : ($named->code ?? null),
                        'name' => $this->localizedName($named, $locale),
                    ];
                }
            }
        }

        return ['data' => $data, 'totals' => $totals, 'place' => $place];
    }

    /**
     * One page of `limit` hits plus the engine's total for the same query and filters. Meilisearch: `page` +
     * `hitsPerPage` make the count exhaustive (`totalHits`, capped by the index's `pagination.maxTotalHits`).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     * @param  array<string, string|int>  $wheres
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: int}
     */
    private function searchModels(string $class, string $query, array $wheres, int $limit): array
    {
        $builder = $class::search($query);
        foreach ($wheres as $field => $value) {
            $builder->where($field, $value);
        }

        $engine = $builder->model->searchableUsing();
        $raw = $engine->paginate($builder, $limit, 1);
        $models = $engine->map($builder, $raw, $builder->model);

        return [$models, (int) $engine->getTotalCount($raw)];
    }

    /**
     * Everything of one type inside the named place, as the place pages count it: active beaches (most viewed
     * first, as "Browse all beaches"), or towns/islands/regions with at least one active beach (most beaches first).
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     * @param  array<string, string|int>  $wheres  column => value
     * @return array{0: \Illuminate\Database\Eloquent\Collection, 1: int}
     */
    private function placeModels(string $class, array $wheres, int $limit): array
    {
        $activeBeaches = fn ($q) => $q->where('status_id', 1);

        $query = $class === Beach::class
            ? Beach::query()->where('status_id', 1)
            : $class::query()->whereHas('beaches', $activeBeaches);
        foreach ($wheres as $column => $value) {
            $query->where($query->getModel()->qualifyColumn($column), $value);
        }

        $total = (clone $query)->count();

        $class === Beach::class
            ? $query->orderByDesc('beach_views')->orderBy('id')
            : $query->withCount(['beaches' => $activeBeaches])->orderByDesc('beaches_count')->orderBy('id');

        return [$query->limit($limit)->get(), $total];
    }

    /**
     * Filters that limit a lower type to the named place. Field names exist both as DB columns and as
     * filterable index attributes (config/scout.php).
     *
     * @param  array{type: string, id: int, code: ?string}  $place
     * @return array<string, string|int>
     */
    public static function scopeFilters(array $place, string $type): array
    {
        $levels = ['country' => 0, 'region' => 1, 'island' => 2, 'city' => 3, 'beach' => 4];
        if ($levels[$type] <= $levels[$place['type']]) {
            return [];
        }

        return match ($place['type']) {
            'country' => ['country_code' => $place['code']],
            'region' => ['region_id' => $place['id']],
            'island' => ['island_id' => $place['id']],
            'city' => ['city_id' => $place['id']],
        };
    }

    /**
     * The single result whose name (in any language) is the query, ignoring case, diacritics and surrounding
     * spaces. Two places with that name (two towns called "San Marco") -> null: no scope, plain search.
     */
    public static function namedPlace(iterable $models, string $query): ?object
    {
        $needle = self::normalizeName($query);
        if ($needle === '') {
            return null;
        }

        $matches = [];
        foreach ($models as $model) {
            $names = collect($model->translations ?? [])->pluck('name')->push($model->name ?? null)->filter();
            if ($names->contains(fn ($name) => self::normalizeName((string) $name) === $needle)) {
                $matches[] = $model;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($name))));
    }

    private function localizedName(object $model, string $locale): string
    {
        $translations = collect($model->translations ?? []);

        return $translations->firstWhere('locale', $locale)?->name
            ?? $translations->firstWhere('locale', 'en')?->name
            ?? (string) ($model->name ?? $model->code ?? '');
    }

    /** @return array<string, mixed> */
    private function mapBeach(Beach $beach, string $locale): array
    {
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
    }

    /** @return array<string, mixed> */
    private function mapCity(City $city, string $locale): array
    {
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
    }

    /** @return array<string, mixed> */
    private function mapIsland(Island $island, string $locale): array
    {
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
    }

    /** @return array<string, mixed> */
    private function mapRegion(Region $region, string $locale): array
    {
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
    }

    /** @return array<string, mixed> */
    private function mapCountry(Country $country, string $locale): array
    {
        // Get name and slug in current locale, fallback to English
        $translation = $country->translations->firstWhere('locale', $locale);
        $countryName = $translation?->name ?? $country->translations->firstWhere('locale', 'en')?->name ?? $country->name;
        $slug = $translation?->slug ?? $country->translations->firstWhere('locale', 'en')?->slug ?? $country->code;

        // Get continent slug and localized name for URL building
        $continentTranslation = $country->continent?->translations->firstWhere('locale', $locale);
        $continentSlug = $continentTranslation?->slug ?? $country->continent?->translations->firstWhere('locale', 'en')?->slug;
        $continentName = $continentTranslation?->name ?? $country->continent?->translations->firstWhere('locale', 'en')?->name ?? $country->continent?->name ?? '';

        return [
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
        ];
    }
}
