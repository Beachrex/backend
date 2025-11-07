<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Waterbody;
use App\Models\UserType;
use App\Models\BeachType;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Filters",
 *     description="API Endpoints for beach filter options"
 * )
 */
class FilterController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/beaches/filters",
     *     operationId="getBeachFilters",
     *     summary="Get all beach filter options",
     *     description="Returns all available filter options for beaches including waterbody types, user types, etc. All data is translated based on Accept-Language header with English fallback.",
     *     tags={"Filters"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
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
     *                     @OA\Property(property="icon", type="string", example=null, description="Icon file path (SVG/PNG)"),
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
     *                     @OA\Property(property="icon", type="string", example=null, description="Icon file path (SVG/PNG)"),
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
    public function index(): JsonResponse
    {
        // Get locale from middleware (SetLocaleFromHeader handles si->sl mapping)
        $locale = app()->getLocale();

        $filters = [];

        // Build country filter from database
        $countryData = $this->getCountryFilterData($locale);
        if ($countryData) {
            $filters['country'] = $countryData;
        }

        // Build region filter from database
        $regionData = $this->getRegionFilterData($locale);
        if ($regionData) {
            $filters['region'] = $regionData;
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
    private function getCountryFilterData(string $locale): ?array
    {
        // Get countries that have more than 1 beach
        $countries = Country::with('translations')
            ->select('countries.id', 'countries.code')
            ->join('beach', 'beach.country_code', '=', 'countries.code')
            ->where('beach.status_id', 1)
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
    private function getRegionFilterData(string $locale): ?array
    {
        // Get regions that have more than 1 beach
        $regions = Region::with('translations')
            ->select('region.id', 'region.code')
            ->join('beach', 'beach.region_id', '=', 'region.id')
            ->where('beach.status_id', 1)
            ->where('region.status_id', 1)
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
}
