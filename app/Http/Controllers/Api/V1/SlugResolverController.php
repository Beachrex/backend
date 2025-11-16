<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Island;
use App\Models\City;
use App\Models\Beach;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlugResolverController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/resolve-slug/{slug}",
     *     operationId="resolveSlug",
     *     tags={"Slug Resolver"},
     *     summary="Resolve a slug to determine its entity type",
     *     description="Determines whether a slug represents a region, island, city, or beach. This is useful for dynamic routing where the URL structure can vary. Returns the entity type and basic information.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="The slug to resolve (e.g., kvarner, island-cres, split)",
     *         required=true,
     *         @OA\Schema(type="string", example="kvarner")
     *     ),
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         description="Optional country code to narrow down the search (e.g., HR, SI, IT)",
     *         required=false,
     *         @OA\Schema(type="string", example="HR")
     *     ),
     *     @OA\Parameter(
     *         name="parent_slug",
     *         in="query",
     *         description="Optional parent slug to provide context (e.g., if resolving a city within an island, pass the island slug)",
     *         required=false,
     *         @OA\Schema(type="string", example="island-cres")
     *     ),
     *     @OA\Parameter(
     *         name="segment_count",
     *         in="query",
     *         description="Optional number of segments in the URL path to help determine entity type (1=region/island, 2=city, etc.)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Slug resolved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", enum={"region", "island", "city", "beach"}, example="region"),
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="slug", type="string", example="kvarner"),
     *                 @OA\Property(property="name", type="string", example="Kvarner"),
     *                 @OA\Property(property="code", type="string", example="KV", description="Only for regions"),
     *                 @OA\Property(property="country_code", type="string", example="HR", description="Country code"),
     *                 @OA\Property(property="beaches_count", type="integer", example=45, description="Number of beaches")
     *             ),
     *             @OA\Property(property="message", type="string", example="Slug resolved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Slug not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Slug not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred while resolving the slug")
     *         )
     *     )
     * )
     */
    public function resolve(Request $request, string $slug): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $countryCode = $request->get('country_code');
            $parentSlug = $request->get('parent_slug');
            $segmentCount = $request->get('segment_count');

            // Build cache key including all context parameters
            // This ensures different contexts get different cache entries
            $cacheKey = "slug_resolver:{$locale}:{$slug}"
                . ($countryCode ? ":{$countryCode}" : '')
                . ($parentSlug ? ":parent:{$parentSlug}" : '')
                . ($segmentCount ? ":segments:{$segmentCount}" : '');

            $result = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale, $countryCode, $parentSlug, $segmentCount) {
                // Optimize search order based on URL segment count
                // This reduces unnecessary database queries by checking most likely types first
                //
                // URL patterns:
                // - 1 segment:  /kvarner              → likely region or island
                // - 2 segments: /island-cres/cres     → likely city or beach
                // - 3+ segments: /kvarner/cres/beach  → likely beach

                $checkOrder = ['region', 'island', 'city', 'beach'];

                if ($segmentCount === 1) {
                    // Single segment: prioritize regions and islands
                    $checkOrder = ['region', 'island', 'city', 'beach'];
                } elseif ($segmentCount === 2) {
                    // Two segments: prioritize cities and beaches
                    $checkOrder = ['city', 'beach', 'island', 'region'];
                } elseif ($segmentCount >= 3) {
                    // Three or more segments: almost always a beach
                    $checkOrder = ['beach', 'city', 'island', 'region'];
                }

                // Check each entity type in optimized order
                foreach ($checkOrder as $type) {
                    $result = null;

                    switch ($type) {
                        case 'region':
                            $result = $this->checkRegion($slug, $locale, $countryCode);
                            break;
                        case 'island':
                            $result = $this->checkIsland($slug, $locale, $parentSlug);
                            break;
                        case 'city':
                            // parent_slug helps resolve ambiguity when island and city have same name
                            $result = $this->checkCity($slug, $locale, $countryCode, $parentSlug);
                            break;
                        case 'beach':
                            $result = $this->checkBeach($slug, $locale, $countryCode);
                            break;
                    }

                    // Return first match found
                    if ($result) {
                        return $result;
                    }
                }

                return null;
            });

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slug not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Slug resolved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resolving the slug',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if slug matches a region
     *
     * @param string $slug The slug to check
     * @param string $locale Current locale for translation lookup
     * @param string|null $countryCode Optional country code to narrow search
     * @return array|null Region data if found, null otherwise
     */
    private function checkRegion(string $slug, string $locale, ?string $countryCode): ?array
    {
        $region = Region::with(['country.translations', 'translations'])
            ->withCount(['beaches' => function ($q) {
                $q->where('status_id', 1);
            }])
            ->whereHas('translations', function ($q) use ($slug, $locale) {
                $q->where('slug', $slug)->where('locale', $locale);
            });

        if ($countryCode) {
            $region->byCountry($countryCode);
        }

        $region = $region->first(['id', 'code', 'country_code', 'status_id']);

        if ($region) {
            return [
                'type' => 'region',
                'id' => $region->id,
                'slug' => $slug,
                'name' => $region->name,
                'code' => $region->code,
                'country_code' => $region->country_code,
                'beaches_count' => $region->beaches_count,
            ];
        }

        return null;
    }

    /**
     * Check if slug matches an island
     *
     * @param string $slug The slug to check
     * @param string $locale Current locale for translation lookup
     * @param string|null $parentSlug Not used for islands (reserved for future use)
     * @return array|null Island data if found, null otherwise
     */
    private function checkIsland(string $slug, string $locale, ?string $parentSlug): ?array
    {
        $island = Island::with(['translations'])
            ->withCount(['beaches' => function ($q) {
                $q->where('status_id', 1);
            }])
            ->whereHas('translations', function ($q) use ($slug, $locale) {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->first(['id', 'status_id']);

        if ($island) {
            return [
                'type' => 'island',
                'id' => $island->id,
                'slug' => $slug,
                'name' => $island->name,
                'beaches_count' => $island->beaches_count,
            ];
        }

        return null;
    }

    /**
     * Check if slug matches a city
     *
     * @param string $slug The slug to check
     * @param string $locale Current locale for translation lookup
     * @param string|null $countryCode Optional country code to narrow search
     * @param string|null $parentSlug Not used for cities (cities don't have parent relationship in database)
     * @return array|null City data if found, null otherwise
     */
    private function checkCity(string $slug, string $locale, ?string $countryCode, ?string $parentSlug): ?array
    {
        $city = City::with(['country.translations', 'translations'])
            ->withCount(['beaches' => function ($q) {
                $q->where('status_id', 1);
            }])
            ->whereHas('translations', function ($q) use ($slug, $locale) {
                $q->where('slug', $slug)->where('locale', $locale);
            });

        if ($countryCode) {
            $city->byCountry($countryCode);
        }

        $city = $city->first(['id', 'country_code', 'region_id', 'status_id']);

        if ($city) {
            return [
                'type' => 'city',
                'id' => $city->id,
                'slug' => $slug,
                'name' => $city->name,
                'country_code' => $city->country_code,
                'beaches_count' => $city->beaches_count,
            ];
        }

        return null;
    }

    /**
     * Check if slug matches a beach
     *
     * @param string $slug The slug to check
     * @param string $locale Current locale for translation lookup
     * @param string|null $countryCode Optional country code to narrow search
     * @return array|null Beach data if found, null otherwise
     */
    private function checkBeach(string $slug, string $locale, ?string $countryCode): ?array
    {
        $beach = Beach::with(['translations'])
            ->whereHas('translations', function ($q) use ($slug, $locale) {
                $q->where('slug', $slug)->where('locale', $locale);
            })
            ->where('status_id', 1);

        if ($countryCode) {
            $beach->where('country_code', $countryCode);
        }

        $beach = $beach->first(['id', 'country_code', 'region_id', 'island_id', 'city_id', 'status_id']);

        if ($beach) {
            return [
                'type' => 'beach',
                'id' => $beach->id,
                'slug' => $slug,
                'name' => $beach->name,
                'country_code' => $beach->country_code,
            ];
        }

        return null;
    }
}

