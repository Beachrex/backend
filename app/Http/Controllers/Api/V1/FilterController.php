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
use App\Models\AccommodationAround;
use App\Models\CulinaryOffer;
use App\Models\LocationInfrastructure;
use App\Models\NaturalLocationCondition;
use App\Models\SportingActivities;
use App\Models\UnderwaterCharacteristic;
use App\Models\RecommendedTransportType;
use App\Models\Accessibility;
use App\Models\AreaDescription;
use App\Models\ParkingCapacity;
use App\Models\BeachInclination;
use App\Models\SwimmingDepthOfOneM;
use App\Models\OccupancyRate;
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
     *     description="Returns available filter options for beaches. When geographic filters (continent_code, country, region, island) are provided, returns only options that have active beaches in those locations. All data is translated based on Accept-Language header with English fallback. Additional filters may include: accommodation_around, culinary_offer, location_infrastructure, natural_location_condition, sporting_activities, underwater_characteristic, recommended_transport_type, accessibility, area_description, parking_capacity, beach_inclination, swimming_depth_of_one_m, occupancy_rate, parking (boolean), has_swimming_pool (boolean), pool_type (indoor/outdoor).",
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
     *         description="Filter by country code or ID (e.g., HR, SI, or numeric ID). Alias: country_code",
     *         required=false,
     *         @OA\Schema(oneOf={@OA\Schema(type="string"), @OA\Schema(type="integer")})
     *     ),
     *     @OA\Parameter(
     *         name="region",
     *         in="query",
     *         description="Filter by region ID or code. Alias: region_code",
     *         required=false,
     *         @OA\Schema(oneOf={@OA\Schema(type="integer"), @OA\Schema(type="string")})
     *     ),
     *     @OA\Parameter(
     *         name="island",
     *         in="query",
     *         description="Filter by island ID or code. Alias: island_code",
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
     *                 ),
     *                 @OA\Property(
     *                     property="accommodation_around",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Accommodation Around", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", nullable=true, description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="checkbox", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Accommodation types available near the beach",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="hotel"),
     *                             @OA\Property(property="name", type="string", example="Hotel"),
     *                             @OA\Property(property="slug", type="string", example="hotel")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="parking",
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Parking", description="Localized filter title"),
     *                     @OA\Property(property="icon", type="string", nullable=true, description="Icon file path (SVG/PNG)"),
     *                     @OA\Property(property="input_type", type="string", example="checkbox", description="Input type: checkbox, radio, select, single, multiple"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         description="Boolean filter options",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="1"),
     *                             @OA\Property(property="label", type="string", example="Yes"),
     *                             @OA\Property(property="icon", type="string", nullable=true)
     *                         )
     *                     )
     *                 ),
     *                 description="Additional filters may be returned based on available data: culinary_offer, location_infrastructure, natural_location_condition, sporting_activities, underwater_characteristic, recommended_transport_type, accessibility, area_description, parking_capacity, beach_inclination, swimming_depth_of_one_m, occupancy_rate, has_swimming_pool, pool_type. All filters follow the same structure with title, icon, input_type/type, and options array."
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
        // The *_code names are accepted as aliases, matching /beaches (BeachFilterService).
        $countryParam = $request->get('country', $request->get('country_code'));
        $regionParam = $request->get('region', $request->get('region_code'));
        $islandParam = $request->get('island', $request->get('island_code'));

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

            // Build accommodation_around filter from database
            $accommodationData = $this->getAccommodationAroundFilterData($locale);
            if ($accommodationData) {
                $filters['accommodation_around'] = $accommodationData;
            }

            // Build culinary_offer filter from database
            $culinaryData = $this->getCulinaryOfferFilterData($locale);
            if ($culinaryData) {
                $filters['culinary_offer'] = $culinaryData;
            }

            // Build location_infrastructure filter from database
            $infrastructureData = $this->getLocationInfrastructureFilterData($locale);
            if ($infrastructureData) {
                $filters['location_infrastructure'] = $infrastructureData;
            }

            // Build natural_location_condition filter from database
            $naturalConditionData = $this->getNaturalLocationConditionFilterData($locale);
            if ($naturalConditionData) {
                $filters['natural_location_condition'] = $naturalConditionData;
            }

            // Build sporting_activities filter from database
            $sportingData = $this->getSportingActivitiesFilterData($locale);
            if ($sportingData) {
                $filters['sporting_activities'] = $sportingData;
            }

            // Build underwater_characteristic filter from database
            $underwaterData = $this->getUnderwaterCharacteristicFilterData($locale);
            if ($underwaterData) {
                $filters['underwater_characteristic'] = $underwaterData;
            }

            // Build recommended_transport_type filter from database
            $transportData = $this->getRecommendedTransportTypeFilterData($locale);
            if ($transportData) {
                $filters['recommended_transport_type'] = $transportData;
            }

            // Build accessibility filter from database
            $accessibilityData = $this->getAccessibilityFilterData($locale);
            if ($accessibilityData) {
                $filters['accessibility'] = $accessibilityData;
            }

            // Build area_description filter from database
            $areaData = $this->getAreaDescriptionFilterData($locale);
            if ($areaData) {
                $filters['area_description'] = $areaData;
            }

            // Build parking_capacity filter from database
            $parkingCapacityData = $this->getParkingCapacityFilterData($locale);
            if ($parkingCapacityData) {
                $filters['parking_capacity'] = $parkingCapacityData;
            }

            // Build beach_inclination filter from database
            $inclinationData = $this->getBeachInclinationFilterData($locale);
            if ($inclinationData) {
                $filters['beach_inclination'] = $inclinationData;
            }

            // Build swimming_depth_of_one_m filter from database
            $depthData = $this->getSwimmingDepthFilterData($locale);
            if ($depthData) {
                $filters['swimming_depth_of_one_m'] = $depthData;
            }

            // Build occupancy_rate filter from database
            $occupancyData = $this->getOccupancyRateFilterData($locale);
            if ($occupancyData) {
                $filters['occupancy_rate'] = $occupancyData;
            }

            // Add boolean filters
            $parkingData = $this->getParkingFilterData($locale);
            if ($parkingData) {
                $filters['parking'] = $parkingData;
            }

            $swimmingPoolData = $this->getSwimmingPoolFilterData($locale);
            if ($swimmingPoolData) {
                $filters['has_swimming_pool'] = $swimmingPoolData;
            }

            $filters['pool_type'] = $this->getPoolTypeFilterData($locale);

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

    /**
     * Get accommodation around filter data
     */
    private function getAccommodationAroundFilterData(string $locale): ?array
    {
        $firstAccommodation = AccommodationAround::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstAccommodation) {
            return null;
        }

        $translation = $firstAccommodation->translations->firstWhere('locale', $locale);
        $translationEn = $firstAccommodation->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Accommodation',
            'icon' => $firstAccommodation->icon,
            'input_type' => $firstAccommodation->input_type,
            'options' => $this->getAccommodationAroundFilters($locale),
        ];
    }

    /**
     * Get accommodation around filter options
     */
    private function getAccommodationAroundFilters(string $locale): array
    {
        $accommodations = AccommodationAround::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $accommodations->map(function ($accommodation) use ($locale) {
            $translation = $accommodation->translations->firstWhere('locale', $locale);
            $translationEn = $accommodation->translations->firstWhere('locale', 'en');

            return [
                'id' => $accommodation->id,
                'code' => $accommodation->code,
                'name' => $translation->name ?? $translationEn->name ?? $accommodation->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get culinary offer filter data
     */
    private function getCulinaryOfferFilterData(string $locale): ?array
    {
        $firstOffer = CulinaryOffer::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstOffer) {
            return null;
        }

        $translation = $firstOffer->translations->firstWhere('locale', $locale);
        $translationEn = $firstOffer->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Culinary Offer',
            'icon' => $firstOffer->icon,
            'input_type' => $firstOffer->input_type,
            'options' => $this->getCulinaryOfferFilters($locale),
        ];
    }

    /**
     * Get culinary offer filter options
     */
    private function getCulinaryOfferFilters(string $locale): array
    {
        $offers = CulinaryOffer::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $offers->map(function ($offer) use ($locale) {
            $translation = $offer->translations->firstWhere('locale', $locale);
            $translationEn = $offer->translations->firstWhere('locale', 'en');

            return [
                'id' => $offer->id,
                'code' => $offer->code,
                'name' => $translation->name ?? $translationEn->name ?? $offer->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get location infrastructure filter data
     */
    private function getLocationInfrastructureFilterData(string $locale): ?array
    {
        $firstInfrastructure = LocationInfrastructure::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstInfrastructure) {
            return null;
        }

        $translation = $firstInfrastructure->translations->firstWhere('locale', $locale);
        $translationEn = $firstInfrastructure->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Infrastructure',
            'icon' => $firstInfrastructure->icon,
            'input_type' => $firstInfrastructure->input_type,
            'options' => $this->getLocationInfrastructureFilters($locale),
        ];
    }

    /**
     * Get location infrastructure filter options
     */
    private function getLocationInfrastructureFilters(string $locale): array
    {
        $infrastructures = LocationInfrastructure::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $infrastructures->map(function ($infrastructure) use ($locale) {
            $translation = $infrastructure->translations->firstWhere('locale', $locale);
            $translationEn = $infrastructure->translations->firstWhere('locale', 'en');

            return [
                'id' => $infrastructure->id,
                'code' => $infrastructure->code,
                'name' => $translation->name ?? $translationEn->name ?? $infrastructure->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get natural location condition filter data
     */
    private function getNaturalLocationConditionFilterData(string $locale): ?array
    {
        $firstCondition = NaturalLocationCondition::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstCondition) {
            return null;
        }

        $translation = $firstCondition->translations->firstWhere('locale', $locale);
        $translationEn = $firstCondition->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Natural Conditions',
            'icon' => $firstCondition->icon,
            'input_type' => $firstCondition->input_type,
            'options' => $this->getNaturalLocationConditionFilters($locale),
        ];
    }

    /**
     * Get natural location condition filter options
     */
    private function getNaturalLocationConditionFilters(string $locale): array
    {
        $conditions = NaturalLocationCondition::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $conditions->map(function ($condition) use ($locale) {
            $translation = $condition->translations->firstWhere('locale', $locale);
            $translationEn = $condition->translations->firstWhere('locale', 'en');

            return [
                'id' => $condition->id,
                'code' => $condition->code,
                'name' => $translation->name ?? $translationEn->name ?? $condition->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get sporting activities filter data
     */
    private function getSportingActivitiesFilterData(string $locale): ?array
    {
        $firstActivity = SportingActivities::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstActivity) {
            return null;
        }

        $translation = $firstActivity->translations->firstWhere('locale', $locale);
        $translationEn = $firstActivity->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Activities',
            'icon' => $firstActivity->icon,
            'input_type' => $firstActivity->input_type,
            'options' => $this->getSportingActivitiesFilters($locale),
        ];
    }

    /**
     * Get sporting activities filter options
     */
    private function getSportingActivitiesFilters(string $locale): array
    {
        $activities = SportingActivities::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $activities->map(function ($activity) use ($locale) {
            $translation = $activity->translations->firstWhere('locale', $locale);
            $translationEn = $activity->translations->firstWhere('locale', 'en');

            return [
                'id' => $activity->id,
                'code' => $activity->code,
                'name' => $translation->name ?? $translationEn->name ?? $activity->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get underwater characteristic filter data
     */
    private function getUnderwaterCharacteristicFilterData(string $locale): ?array
    {
        $firstCharacteristic = UnderwaterCharacteristic::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstCharacteristic) {
            return null;
        }

        $translation = $firstCharacteristic->translations->firstWhere('locale', $locale);
        $translationEn = $firstCharacteristic->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Underwater',
            'icon' => $firstCharacteristic->icon,
            'input_type' => $firstCharacteristic->input_type,
            'options' => $this->getUnderwaterCharacteristicFilters($locale),
        ];
    }

    /**
     * Get underwater characteristic filter options
     */
    private function getUnderwaterCharacteristicFilters(string $locale): array
    {
        $characteristics = UnderwaterCharacteristic::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $characteristics->map(function ($characteristic) use ($locale) {
            $translation = $characteristic->translations->firstWhere('locale', $locale);
            $translationEn = $characteristic->translations->firstWhere('locale', 'en');

            return [
                'id' => $characteristic->id,
                'code' => $characteristic->code,
                'name' => $translation->name ?? $translationEn->name ?? $characteristic->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get recommended transport type filter data
     */
    private function getRecommendedTransportTypeFilterData(string $locale): ?array
    {
        $firstTransport = RecommendedTransportType::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstTransport) {
            return null;
        }

        $translation = $firstTransport->translations->firstWhere('locale', $locale);
        $translationEn = $firstTransport->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Transport',
            'icon' => $firstTransport->icon,
            'input_type' => $firstTransport->input_type,
            'options' => $this->getRecommendedTransportTypeFilters($locale),
        ];
    }

    /**
     * Get recommended transport type filter options
     */
    private function getRecommendedTransportTypeFilters(string $locale): array
    {
        $transports = RecommendedTransportType::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $transports->map(function ($transport) use ($locale) {
            $translation = $transport->translations->firstWhere('locale', $locale);
            $translationEn = $transport->translations->firstWhere('locale', 'en');

            return [
                'id' => $transport->id,
                'code' => $transport->code,
                'name' => $translation->name ?? $translationEn->name ?? $transport->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get accessibility filter data
     */
    private function getAccessibilityFilterData(string $locale): ?array
    {
        $firstAccessibility = Accessibility::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstAccessibility) {
            return null;
        }

        $translation = $firstAccessibility->translations->firstWhere('locale', $locale);
        $translationEn = $firstAccessibility->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Accessibility',
            'icon' => $firstAccessibility->icon,
            'input_type' => $firstAccessibility->input_type,
            'options' => $this->getAccessibilityFilters($locale),
        ];
    }

    /**
     * Get accessibility filter options
     */
    private function getAccessibilityFilters(string $locale): array
    {
        $accessibilities = Accessibility::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $accessibilities->map(function ($accessibility) use ($locale) {
            $translation = $accessibility->translations->firstWhere('locale', $locale);
            $translationEn = $accessibility->translations->firstWhere('locale', 'en');

            return [
                'id' => $accessibility->id,
                'code' => $accessibility->code,
                'name' => $translation->name ?? $translationEn->name ?? $accessibility->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get area description filter data
     */
    private function getAreaDescriptionFilterData(string $locale): ?array
    {
        $firstArea = AreaDescription::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstArea) {
            return null;
        }

        $translation = $firstArea->translations->firstWhere('locale', $locale);
        $translationEn = $firstArea->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Area Type',
            'icon' => $firstArea->icon,
            'input_type' => $firstArea->input_type,
            'options' => $this->getAreaDescriptionFilters($locale),
        ];
    }

    /**
     * Get area description filter options
     */
    private function getAreaDescriptionFilters(string $locale): array
    {
        $areas = AreaDescription::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $areas->map(function ($area) use ($locale) {
            $translation = $area->translations->firstWhere('locale', $locale);
            $translationEn = $area->translations->firstWhere('locale', 'en');

            return [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $translation->name ?? $translationEn->name ?? $area->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get parking capacity filter data
     */
    private function getParkingCapacityFilterData(string $locale): ?array
    {
        $firstCapacity = ParkingCapacity::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstCapacity) {
            return null;
        }

        $translation = $firstCapacity->translations->firstWhere('locale', $locale);
        $translationEn = $firstCapacity->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Parking Capacity',
            'icon' => $firstCapacity->icon,
            'input_type' => $firstCapacity->input_type,
            'options' => $this->getParkingCapacityFilters($locale),
        ];
    }

    /**
     * Get parking capacity filter options
     */
    private function getParkingCapacityFilters(string $locale): array
    {
        $capacities = ParkingCapacity::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $capacities->map(function ($capacity) use ($locale) {
            $translation = $capacity->translations->firstWhere('locale', $locale);
            $translationEn = $capacity->translations->firstWhere('locale', 'en');

            return [
                'id' => $capacity->id,
                'code' => $capacity->code,
                'name' => $translation->name ?? $translationEn->name ?? $capacity->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get beach inclination filter data
     */
    private function getBeachInclinationFilterData(string $locale): ?array
    {
        $firstInclination = BeachInclination::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstInclination) {
            return null;
        }

        $translation = $firstInclination->translations->firstWhere('locale', $locale);
        $translationEn = $firstInclination->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Beach Inclination',
            'icon' => $firstInclination->icon,
            'input_type' => $firstInclination->input_type,
            'options' => $this->getBeachInclinationFilters($locale),
        ];
    }

    /**
     * Get beach inclination filter options
     */
    private function getBeachInclinationFilters(string $locale): array
    {
        $inclinations = BeachInclination::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $inclinations->map(function ($inclination) use ($locale) {
            $translation = $inclination->translations->firstWhere('locale', $locale);
            $translationEn = $inclination->translations->firstWhere('locale', 'en');

            return [
                'id' => $inclination->id,
                'code' => $inclination->code,
                'name' => $translation->name ?? $translationEn->name ?? $inclination->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get swimming depth filter data
     */
    private function getSwimmingDepthFilterData(string $locale): ?array
    {
        $firstDepth = SwimmingDepthOfOneM::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstDepth) {
            return null;
        }

        $translation = $firstDepth->translations->firstWhere('locale', $locale);
        $translationEn = $firstDepth->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Swimming Depth',
            'icon' => $firstDepth->icon,
            'input_type' => $firstDepth->input_type,
            'options' => $this->getSwimmingDepthFilters($locale),
        ];
    }

    /**
     * Get swimming depth filter options
     */
    private function getSwimmingDepthFilters(string $locale): array
    {
        $depths = SwimmingDepthOfOneM::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $depths->map(function ($depth) use ($locale) {
            $translation = $depth->translations->firstWhere('locale', $locale);
            $translationEn = $depth->translations->firstWhere('locale', 'en');

            return [
                'id' => $depth->id,
                'code' => $depth->code,
                'name' => $translation->name ?? $translationEn->name ?? $depth->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get occupancy rate filter data
     */
    private function getOccupancyRateFilterData(string $locale): ?array
    {
        $firstRate = OccupancyRate::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->first();

        if (!$firstRate) {
            return null;
        }

        $translation = $firstRate->translations->firstWhere('locale', $locale);
        $translationEn = $firstRate->translations->firstWhere('locale', 'en');

        return [
            'title' => $translation->filter_title ?? $translationEn->filter_title ?? 'Occupancy',
            'icon' => $firstRate->icon,
            'input_type' => $firstRate->input_type,
            'options' => $this->getOccupancyRateFilters($locale),
        ];
    }

    /**
     * Get occupancy rate filter options
     */
    private function getOccupancyRateFilters(string $locale): array
    {
        $rates = OccupancyRate::with('translations')
            ->active()
            ->showInFilter()
            ->orderBy('filter_order')
            ->orderBy('code')
            ->get();

        return $rates->map(function ($rate) use ($locale) {
            $translation = $rate->translations->firstWhere('locale', $locale);
            $translationEn = $rate->translations->firstWhere('locale', 'en');

            return [
                'id' => $rate->id,
                'code' => $rate->code,
                'name' => $translation->name ?? $translationEn->name ?? $rate->code,
                'slug' => $translation->slug ?? $translationEn->slug ?? null,
            ];
        })->toArray();
    }

    /**
     * Get parking filter data (boolean). Title and label in the request language (mobile report 2026-10-09).
     */
    private function getParkingFilterData(string $locale): ?array
    {
        return [
            'title' => __('Parking', [], $locale),
            'icon' => null,
            'input_type' => 'checkbox',
            // Same option shape as the other filters (code/name), so clients can match the selected value.
            'options' => [
                ['code' => '1', 'value' => '1', 'label' => __('Yes', [], $locale), 'name' => __('Yes', [], $locale), 'icon' => null],
            ],
        ];
    }

    /**
     * Pool type filter (QA #70): indoor / outdoor, from the pool counts set in the admin.
     * A beach with both kinds matches either option.
     */
    private function getPoolTypeFilterData(string $locale): array
    {
        return [
            'title' => __('Pool type', [], $locale),
            'icon' => null,
            'input_type' => 'checkbox',
            'options' => [
                ['code' => 'indoor', 'value' => 'indoor', 'label' => __('Indoor pool', [], $locale), 'name' => __('Indoor pool', [], $locale), 'icon' => null],
                ['code' => 'outdoor', 'value' => 'outdoor', 'label' => __('Outdoor pool', [], $locale), 'name' => __('Outdoor pool', [], $locale), 'icon' => null],
            ],
        ];
    }

    /**
     * Get swimming pool filter data (boolean)
     */
    private function getSwimmingPoolFilterData(string $locale): ?array
    {
        return [
            'title' => __('Swimming pool', [], $locale),
            'icon' => null,
            'input_type' => 'checkbox',
            // Same option shape as the other filters (code/name), so clients can match the selected value.
            'options' => [
                ['code' => '1', 'value' => '1', 'label' => __('Yes', [], $locale), 'name' => __('Yes', [], $locale), 'icon' => null],
            ],
        ];
    }
}
