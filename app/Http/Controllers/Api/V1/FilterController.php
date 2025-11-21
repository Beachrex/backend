<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Waterbody;
use App\Models\UserType;
use App\Models\BeachType;
use App\Models\Country;
use App\Models\Region;
use App\Models\Island;
use App\Models\City;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Filters",
 *     description="API Endpoints for beach filter options"
 * )
 */
class FilterController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/beaches/filters",
     *     operationId="getBeachFilters",
     *     summary="Get beach filter options with optional cascading filters",
     *     description="Returns available filter options for beaches. When geographic filters (continent_code, country, region, island) are provided, returns only options that have active beaches in those locations. All data is translated based on Accept-Language header with English fallback.",
     *     tags={"Filters"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="continent_code",
     *         in="query",
     *         description="Filter by continent code (e.g., EU, AS, AF)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country code or ID (e.g., HR, SI, or numeric ID)",
     *         required=false,
     *         @OA\Schema(oneOf={@OA\Schema(type="string"), @OA\Schema(type="integer")})
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region ID or code",
     *         required=false,
     *         @OA\Schema(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")})
     *     ),
     *     @OA\Parameter(
     *         name="island",
     *         in="query",
     *         description="Filter by island ID or code",
     *         required=false,
     *         @OA\Schema(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Filter options retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="country",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Country", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", nullable=true, description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="select", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Countries with more than 1 beach",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="SI"),
     *                             @OA\Property(property="name", type="string", example="Slovenia"),
     *                             @OA\Property(property="slug", type="string", example="slovenia")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="region",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Region", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", nullable=true, description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="select", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Regions with more than 1 beach",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="obalno-kraska"),
     *                             @OA\Property(property="name", type="string", example="Obalno-kraška"),
     *                             @OA\Property(property="slug", type="string", example="obalno-kraska")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="island",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Island", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", nullable=true, description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="select", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Islands with more than 1 beach",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="krk"),
     *                             @OA\Property(property="name", type="string", example="Krk"),
     *                             @OA\Property(property="slug", type="string", example="krk")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="waterbody",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Water Body", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", example="icons/filters/waterbody.svg", description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="checkbox", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Water body types (sea, lake, river, pool, etc.)",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="sea"),
     *                             @OA\Property(property="name", type="string", example="Sea"),
     *                             @OA\Property(property="slug", type="string", example="sea")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="user_type",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="User Type", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", example="icons/filters/user_type.svg", description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="checkbox", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="User types (adventurer, family, youth, etc.)",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="family"),
     *                             @OA\Property(property="name", type="string", example="Family"),
     *                             @OA\Property(property="slug", type="string", example="family")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="beach_type",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Beach Type", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", example="icons/filters/beach_type.svg", description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="checkbox", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Beach types (sand, small_stones, big_stones, rock_beach, grass_beach, concrete, etc.)",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="sand"),
     *                             @OA\Property(property="name", type="string", example="Sand"),
     *                             @OA\Property(property="slug", type="string", example="sand-beaches")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Get locale from middleware (SetLocaleFromHeader handles si->sl mapping)
        $locale = app()->getLocale();

        // Get optional geographic filters for cascading
        $continentCode = $request->get('continent_code');
        $countryParam = $request->get('country');
        $regionParam = $request->get('region');
        $islandParam = $request->get('island');

        // Resolve country parameter (can be code or ID)
        $countryCode = $this->resolveCountryParameter($countryParam);

        // Resolve region parameter (can be code or ID)
        $regionId = $this->resolveRegionParameter($regionParam);

        // Resolve island parameter (can be code or ID)
        $islandId = $this->resolveIslandParameter($islandParam);

        // Generate cache key based on filters
        $cacheKeySuffix = $this->generateCacheKeySuffix($continentCode, $countryCode, $regionId, $islandId);
        $cacheKey = "beaches:filters:{$locale}:{$cacheKeySuffix}";

        // Use cache with filter-aware key
        $filters = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($locale, $continentCode, $countryCode, $regionId, $islandId) {
            $filters = [];

            // Build country filter from database
            $countryData = $this->getCountryFilterData($locale, $continentCode);
            if ($countryData) {
                $filters['country'] = $countryData;
            }

            // Build region filter from database
            $regionData = $this->getRegionFilterData($locale, $countryCode);
            if ($regionData) {
                $filters['region'] = $regionData;
            }

            // Build island filter from database
            $islandData = $this->getIslandFilterData($locale, $countryCode, $regionId);
            if ($islandData) {
                $filters['island'] = $islandData;
            }

            // Build city filter from database
            $cityData = $this->getCityFilterData($locale, $countryCode, $regionId, $islandId);
            if ($cityData) {
                $filters['city'] = $cityData;
            }

            // Build waterbody filter from database
            $waterbodyData = $this->getWaterbodyFilterData($locale);
            if ($waterbodyData) {
                $filters['waterbody'] = $waterbodyData;
            }

            // Build user_type filter from database
            $userTypeData = $this->getUserTypeFilterData($locale);
            if ($userTypeData) {
                $filters['user_type'] = $userTypeData;
            }

            // Build beach_type filter from database
            $beachTypeData = $this->getBeachTypeFilterData($locale);
            if ($beachTypeData) {
                $filters['beach_type'] = $beachTypeData;
            }

            return $filters;
        });

        return response()->json([
            'data' => $filters
        ]);
    }

    /**
     * Get waterbody filter data with metadata from database
     */
    private function getWaterbodyFilterData(string $locale): ?array
    {
        // Get any waterbody to fetch the filter metadata (icon, title)
        // All waterbodies share the same filter metadata
        $firstWaterbody = Waterbody::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstWaterbody) {
            return null;
        }

        // Get filter title from translation
        $translation = $firstWaterbody->translations->firstWhere('locale', $locale);
        $translationEn = $firstWaterbody->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Water Body',
            'icon' => $firstWaterbody->icon,
            'input_type' => $firstWaterbody->input_type,
            'options' => $this->getWaterbodyFilters($locale),
        ];
    }

    /**
     * Get waterbody filter options
     */
    private function getWaterbodyFilters(string $locale): array
    {
        $waterbodies = Waterbody::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $waterbodies->map(function ($waterbody) use ($locale) {
            $translation = $waterbody->translations->firstWhere('locale', $locale);
            $translationEn = $waterbody->translations->firstWhere('locale', 'en');

            return [
                'id' => $waterbody->id,
                'code' => $waterbody->code,
                'name' => $translation->name ?? $translationEn->name ?? $waterbody->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get user_type filter data with metadata from database
     */
    private function getUserTypeFilterData(string $locale): ?array
    {
        // Get any user type to fetch the filter metadata (icon, title)
        // All user types share the same filter metadata
        $firstUserType = UserType::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstUserType) {
            return null;
        }

        // Get filter title from translation
        $translation = $firstUserType->translations->firstWhere('locale', $locale);
        $translationEn = $firstUserType->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'User Type',
            'icon' => $firstUserType->icon,
            'input_type' => $firstUserType->input_type,
            'options' => $this->getUserTypeFilters($locale),
        ];
    }

    /**
     * Get user_type filter options
     */
    private function getUserTypeFilters(string $locale): array
    {
        $userTypes = UserType::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $userTypes->map(function ($userType) use ($locale) {
            $translation = $userType->translations->firstWhere('locale', $locale);
            $translationEn = $userType->translations->firstWhere('locale', 'en');

            return [
                'id' => $userType->id,
                'code' => $userType->code,
                'name' => $translation->name ?? $translationEn->name ?? $userType->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get beach_type filter data with metadata from database
     */
    private function getBeachTypeFilterData(string $locale): ?array
    {
        // Get any beach type to fetch the filter metadata (icon, title)
        // All beach types share the same filter metadata
        $firstBeachType = BeachType::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstBeachType) {
            return null;
        }

        $translation = $firstBeachType->translations->firstWhere('locale', $locale);
        $translationEn = $firstBeachType->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Beach Type',
            'icon' => $firstBeachType->icon,
            'input_type' => $firstBeachType->input_type,
            'options' => $this->getBeachTypeFilters($locale),
        ];
    }

    /**
     * Get beach_type filter options
     */
    private function getBeachTypeFilters(string $locale): array
    {
        $beachTypes = BeachType::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $beachTypes->map(function ($beachType) use ($locale) {
            $translation = $beachType->translations->firstWhere('locale', $locale);
            $translationEn = $beachType->translations->firstWhere('locale', 'en');

            return [
                'id' => $beachType->id,
                'code' => $beachType->code,
                'name' => $translation->name ?? $translationEn->name ?? $beachType->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get country filter data with metadata
     */
    private function getCountryFilterData(string $locale, ?string $continentCode = null): ?array
    {
        // Get countries that have more than 1 beach
        $query = Country::with('translations')
            ->select('countries.id', 'countries.code')
            ->join('beach', 'beach.country_code', '=', 'countries.code')
            ->where('beach.status_id', 1);

        if ($continentCode) {
            $query->where('countries.continent_code', strtoupper($continentCode));
        }

        $countries = $query
            ->groupBy('countries.id', 'countries.code')
            ->havingRaw('COUNT(beach.id) > 1')
            ->orderBy('countries.code')
            ->get();

        if ($countries->isEmpty()) {
            return null;
        }

        // Get the first country to extract filter metadata
        $firstCountry = $countries->first();
        $translation = $firstCountry->translations->firstWhere('locale', $locale);
        $translationEn = $firstCountry->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Country',
            'icon' => null,
            'input_type' => 'select',
            'options' => $this->getCountryFilters($locale, $countries),
        ];
    }

    /**
     * Get country filter options (only countries with more than 1 beach)
     */
    private function getCountryFilters(string $locale, $countries): array
    {
        return $countries->map(function ($country) use ($locale) {
            $translation = $country->translations->firstWhere('locale', $locale);
            $translationEn = $country->translations->firstWhere('locale', 'en');

            return [
                'id' => $country->id,
                'code' => $country->code,
                'name' => $translation->name ?? $translationEn->name ?? $country->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get region filter data with metadata
     */
    private function getRegionFilterData(string $locale, ?string $countryCode = null): ?array
    {
        // Get regions that have more than 1 beach
        $query = Region::with('translations')
            ->select('region.id', 'region.code')
            ->join('beach', 'beach.region_id', '=', 'region.id')
            ->where('beach.status_id', 1)
            ->where('region.status_id', 1);

        if ($countryCode) {
            $query->where('region.country_code', strtoupper($countryCode));
        }

        $regions = $query
            ->groupBy('region.id', 'region.code')
            ->havingRaw('COUNT(beach.id) > 1')
            ->orderBy('region.code')
            ->get();

        if ($regions->isEmpty()) {
            return null;
        }

        // Get the first region to extract filter metadata
        $firstRegion = $regions->first();
        $translation = $firstRegion->translations->firstWhere('locale', $locale);
        $translationEn = $firstRegion->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Region',
            'icon' => null,
            'input_type' => 'select',
            'options' => $this->getRegionFilters($locale, $regions),
        ];
    }

    /**
     * Get region filter options (only regions with more than 1 beach)
     */
    private function getRegionFilters(string $locale, $regions): array
    {
        return $regions->map(function ($region) use ($locale) {
            $translation = $region->translations->firstWhere('locale', $locale);
            $translationEn = $region->translations->firstWhere('locale', 'en');

            return [
                'id' => $region->id,
                'code' => $region->code,
                'name' => $translation->name ?? $translationEn->name ?? $region->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get island filter data with metadata
     */
    private function getIslandFilterData(string $locale, ?string $countryCode = null, ?int $regionId = null): ?array
    {
        // Get islands that have more than 1 beach
        $query = Island::with('translations')
            ->select('island.id', 'island.code')
            ->join('beach', 'beach.island_id', '=', 'island.id')
            ->where('beach.status_id', 1)
            ->where('island.status_id', 1);

        if ($countryCode) {
            $query->where('island.country_code', strtoupper($countryCode));
        }

        if ($regionId) {
            $query->where('island.region_id', $regionId);
        }

        $islands = $query
            ->groupBy('island.id', 'island.code')
            ->havingRaw('COUNT(beach.id) > 1')
            ->orderBy('island.code')
            ->get();

        if ($islands->isEmpty()) {
            return null;
        }

        // Get the first island to extract filter metadata
        $firstIsland = $islands->first();
        $translation = $firstIsland->translations->firstWhere('locale', $locale);
        $translationEn = $firstIsland->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Island',
            'icon' => null,
            'input_type' => 'select',
            'options' => $this->getIslandFilters($locale, $islands),
        ];
    }

    /**
     * Get island filter options (only islands with more than 1 beach)
     */
    private function getIslandFilters(string $locale, $islands): array
    {
        return $islands->map(function ($island) use ($locale) {
            $translation = $island->translations->firstWhere('locale', $locale);
            $translationEn = $island->translations->firstWhere('locale', 'en');

            return [
                'id' => $island->id,
                'code' => $island->code,
                'name' => $translation->name ?? $translationEn->name ?? $island->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get city filter data with metadata
     */
    private function getCityFilterData(string $locale, ?string $countryCode = null, ?int $regionId = null, ?int $islandId = null): ?array
    {
        // Get cities that have beaches
        $query = City::with('translations')
            ->select('city.id', 'city.code')
            ->join('beach', 'beach.city_id', '=', 'city.id')
            ->where('beach.status_id', 1)
            ->where('city.status_id', 1);

        if ($countryCode) {
            $query->where('city.country_code', strtoupper($countryCode));
        }

        if ($regionId) {
            $query->where('city.region_id', $regionId);
        }

        if ($islandId) {
            $query->where('city.island_id', $islandId);
        }

        $cities = $query
            ->groupBy('city.id', 'city.code')
            ->orderBy('city.code')
            ->get();

        if ($cities->isEmpty()) {
            return null;
        }

        // Get the first city to extract filter metadata
        $firstCity = $cities->first();
        $translation = $firstCity->translations->firstWhere('locale', $locale);
        $translationEn = $firstCity->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'City',
            'icon' => null,
            'input_type' => 'select',
            'options' => $this->getCityFilters($locale, $cities),
        ];
    }

    /**
     * Get city filter options
     */
    private function getCityFilters(string $locale, $cities): array
    {
        return $cities->map(function ($city) use ($locale) {
            $translation = $city->translations->firstWhere('locale', $locale);
            $translationEn = $city->translations->firstWhere('locale', 'en');

            return [
                'id' => $city->id,
                'code' => $city->code,
                'name' => $translation->name ?? $translationEn->name ?? $city->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Resolve country parameter - can be code or ID
     * Returns country code if found, null otherwise
     */
    private function resolveCountryParameter($param): ?string
    {
        if (!$param) {
            return null;
        }

        // If it's numeric, treat as ID
        if (is_numeric($param)) {
            $country = Country::find($param);
            return $country ? $country->code : null;
        }

        // Otherwise treat as code
        return strtoupper($param);
    }

    /**
     * Resolve region parameter - can be code or ID
     * Returns region ID if found, null otherwise
     */
    private function resolveRegionParameter($param): ?int
    {
        if (!$param) {
            return null;
        }

        // If it's numeric, treat as ID
        if (is_numeric($param)) {
            return (int) $param;
        }

        // Otherwise treat as code
        $region = Region::where('code', $param)->first();
        return $region ? $region->id : null;
    }

    /**
     * Resolve island parameter - can be code or ID
     * Returns island ID if found, null otherwise
     */
    private function resolveIslandParameter($param): ?int
    {
        if (!$param) {
            return null;
        }

        // If it's numeric, treat as ID
        if (is_numeric($param)) {
            return (int) $param;
        }

        // Otherwise treat as code
        $island = Island::where('code', $param)->first();
        return $island ? $island->id : null;
    }

    /**
     * Generate cache key suffix from filter parameters
     * Ensures consistent cache keys for the same filter combinations
     */
    private function generateCacheKeySuffix(?string $continentCode = null, ?string $countryCode = null, ?int $regionId = null, ?int $islandId = null): string
    {
        $parts = [];

        if ($continentCode) {
            $parts['continent_code'] = strtoupper($continentCode);
        }

        if ($countryCode) {
            $parts['country'] = strtoupper($countryCode);
        }

        if ($regionId) {
            $parts['region'] = $regionId;
        }

        if ($islandId) {
            $parts['island'] = $islandId;
        }

        if (empty($parts)) {
            return 'all';
        }

        // Sort keys for consistency
        ksort($parts);

        // Build suffix like: continent_code=EU:country=HR:region=1
        $suffix = '';
        foreach ($parts as $key => $value) {
            $suffix .= ($suffix ? ':' : '') . "{$key}={$value}";
        }

        return $suffix;
    }
}
