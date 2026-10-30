<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContinentResource;
use App\Models\Continent;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Traits\CacheAware;

/**
 * @OA\Tag(
 *     name="Continents",
 *     description="API Endpoints for continents"
 * )
 */
class ContinentController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/continents",
     *     operationId="getContinents",
     *     tags={"Continents"},
     *     summary="Get all continents that have beaches",
     *     description="Returns a list of continents that have at least one beach",
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
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="EU"),
     *                     @OA\Property(property="name", type="string", example="Europe"),
     *                     @OA\Property(property="slug", type="string", example="europe"),
     *                     @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg", description="Relative path to main image from most viewed beach"),
     *                     @OA\Property(property="beaches_count", type="integer", example=15),
     *                     @OA\Property(property="accommodation_count", type="integer", example=0, description="Number of accommodations (hardcoded to 0 until booking integration)")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Continents retrieved successfully"
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
    public function index(): JsonResponse
    {
        try {
            // Whole response cached per locale (ApiResponseCache: JSON, not models; cleared on content changes).
            return \App\Support\ApiResponseCache::json('continents.index', [], \App\Support\ApiResponseCache::TTL_REFERENCE, function () {
                // Only continents that have beaches, with beaches count
                $continents = Continent::withBeaches()
                    ->with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->get(['code', 'main_image']);

                return [
                    'success' => true,
                    'data' => ContinentResource::collection($continents),
                    'message' => 'Continents retrieved successfully'
                ];
            });

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving continents',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/continents/{code}",
     *     operationId="getContinent",
     *     tags={"Continents"},
     *     summary="Get a specific continent",
     *     description="Returns a specific continent by code with its beaches count",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Continent code (e.g., EU, AS, NA)",
     *         required=true,
     *         @OA\Schema(type="string", example="EU")
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
     *                 @OA\Property(property="code", type="string", example="EU"),
     *                 @OA\Property(property="name", type="string", example="Europe"),
     *                 @OA\Property(property="slug", type="string", example="europe"),
     *                 @OA\Property(property="main_image", type="string", nullable=true, example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg", description="Relative path to main image from most viewed beach"),
     *                 @OA\Property(property="beaches_count", type="integer", example=15),
                @OA\Property(property="accommodation_count", type="integer", example=0, description="Number of accommodations (hardcoded to 0 until booking integration)"),
     *                 @OA\Property(
     *                     property="navigation",
     *                     type="object",
     *                     description="Navigation to previous and next continents with beaches",
     *                     @OA\Property(
     *                         property="previous",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="code", type="string", example="AS"),
     *                         @OA\Property(property="name", type="string", example="Asia"),
     *                         @OA\Property(property="slug", type="string", example="asia"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="img/continents/asia.jpg"),
     *                         @OA\Property(
     *                             property="location",
     *                             type="object",
     *                             description="Empty object for continents (top level)"
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="next",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="code", type="string", example="NA"),
     *                         @OA\Property(property="name", type="string", example="North America"),
     *                         @OA\Property(property="slug", type="string", example="north-america"),
     *                         @OA\Property(property="main_image", type="string", nullable=true, example="img/continents/north-america.jpg"),
     *                         @OA\Property(
     *                             property="location",
     *                             type="object",
     *                             description="Empty object for continents (top level)"
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Continent retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Continent not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Continent not found")
     *         )
     *     )
     * )
     */
    public function show(string $code, Request $request): JsonResponse
    {
        try {
            $code = strtoupper($code);
            $locale = app()->getLocale();
            $cacheKey = "continent:show:{$locale}:{$code}";

            $data = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($code, $locale, $request) {
                $continent = Continent::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->where('code', $code)
                    ->first(['code', 'main_image']);

                if (!$continent) {
                    return null;
                }

                // Get next and previous continents
                $navigation = $this->getNavigationContinents($continent, $locale);

                $resource = new ContinentResource($continent);
                $resourceArray = $resource->toArray($request);

                // Add navigation to response
                $resourceArray['navigation'] = $navigation;

                return $resourceArray;
            });

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Continent not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Continent retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the continent',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get next and previous continents based on code order
     * Previous = highest code < current code
     * Next = lowest code > current code
     */
    private function getNavigationContinents(Continent $currentContinent, string $locale): array
    {
        // Get previous continent: highest code < current code
        $previousContinent = DB::select("
            SELECT
                c.code,
                c.main_image,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug
            FROM continents c
            LEFT JOIN continent_translation ct ON c.code = ct.continent_code AND ct.locale = ?
            LEFT JOIN continent_translation ct_en ON c.code = ct_en.continent_code AND ct_en.locale = 'en'
            WHERE c.code < ?
              AND EXISTS (
                  SELECT 1 FROM countries co
                  JOIN beach b ON b.country_code = co.code
                  WHERE co.continent_code = c.code
                    AND b.status_id = 1
              )
            ORDER BY c.code DESC
            LIMIT 1
        ", [
            $locale,
            $currentContinent->code,
        ]);

        // Get next continent: lowest code > current code
        $nextContinent = DB::select("
            SELECT
                c.code,
                c.main_image,
                COALESCE(ct.name, ct_en.name) as name,
                COALESCE(ct.slug, ct_en.slug) as slug
            FROM continents c
            LEFT JOIN continent_translation ct ON c.code = ct.continent_code AND ct.locale = ?
            LEFT JOIN continent_translation ct_en ON c.code = ct_en.continent_code AND ct_en.locale = 'en'
            WHERE c.code > ?
              AND EXISTS (
                  SELECT 1 FROM countries co
                  JOIN beach b ON b.country_code = co.code
                  WHERE co.continent_code = c.code
                    AND b.status_id = 1
              )
            ORDER BY c.code ASC
            LIMIT 1
        ", [
            $locale,
            $currentContinent->code,
        ]);

        // Format the results
        $previous = isset($previousContinent[0]) ? [
            'code' => $previousContinent[0]->code,
            'name' => $previousContinent[0]->name,
            'slug' => $previousContinent[0]->slug,
            'main_image' => $previousContinent[0]->main_image,
            'location' => []
        ] : null;

        $next = isset($nextContinent[0]) ? [
            'code' => $nextContinent[0]->code,
            'name' => $nextContinent[0]->name,
            'slug' => $nextContinent[0]->slug,
            'main_image' => $nextContinent[0]->main_image,
            'location' => []
        ] : null;

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }
}
