<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\IslandResource;
use App\Models\Island;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;
use App\Traits\ProvidesWeather;

/**
 * @OA\Tag(
 *     name="Islands",
 *     description="API Endpoints for islands"
 * )
 */
class IslandController extends Controller
{
    use CacheAware, ProvidesWeather;

    /**
     * @OA\Get(
     *     path="/api/v1/islands",
     *     operationId="getIslands",
     *     tags={"Islands"},
     *     summary="Get all islands that have beaches",
     *     description="Returns a list of islands that have at least one beach. Returns minimal data: id, code, name, slug, beaches_count.",
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
     *                 @OA\Items(ref="#/components/schemas/Island")
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Islands retrieved successfully"
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
            $locale = app()->getLocale();
            $cacheKey = "islands:index:{$locale}";

            $islands = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return Island::with('translations')
                    ->withBeaches()
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->get([
                        'id',
                        'code',
                        'status_id'
                    ]);
            });

            return response()->json([
                'success' => true,
                'data' => IslandResource::collection($islands),
                'message' => 'Islands retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving islands',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/islands/{slug}",
     *     operationId="getIsland",
     *     tags={"Islands"},
     *     summary="Get a specific island",
     *     description="Returns a specific island by slug with full details including beaches count, badges, and articles.",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Island slug (e.g., krk, brac, hvar)",
     *         required=true,
     *         @OA\Schema(type="string", example="krk")
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
     *                 ref="#/components/schemas/Island"
     *             ),
     *             @OA\Property(property="message", type="string", example="Island retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Island not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Island not found")
     *         )
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "island:show:{$locale}:{$slug}";

            $island = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale) {
                return Island::with([
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
                    })
                    ->first([
                        'id',
                        'code',
                        'status_id'
                    ]);
            });

            if (!$island) {
                return response()->json([
                    'success' => false,
                    'message' => 'Island not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new IslandResource($island),
                'message' => 'Island retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the island',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/islands/{slug}/weather",
     *     operationId="getIslandWeather",
     *     summary="Get current weather for an island",
     *     description="Returns real-time weather data from OpenWeatherMap for a specific island. Weather data is cached for 1 hour.",
     *     tags={"Islands"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Island slug (e.g., krk, brac, hvar)",
     *         @OA\Schema(type="string", example="hvar")
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
     *                 @OA\Property(property="island_id", type="integer", example=1),
     *                 @OA\Property(property="island_slug", type="string", example="hvar"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="string", example="43.1729"),
     *                     @OA\Property(property="longitude", type="string", example="16.4411")
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
     *                     @OA\Property(property="location_name", type="string", example="Hvar")
     *                 ),
     *                 @OA\Property(property="sea_temperature", type="number", format="float", example=23.5, nullable=true),
     *                 @OA\Property(property="cached_at", type="string", format="date-time", example="2025-11-29T10:17:50+00:00")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Island not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Island not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Island has no coordinates",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Island has no coordinates for weather data")
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
    public function weather(string $slug): JsonResponse
    {
        try {
            $locale = app()->getLocale();

            // Find island by slug
            $island = Island::with('translations')
                ->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)
                      ->whereIn('locale', [$locale, 'en']);
                })
                ->first(['id', 'code', 'latitude', 'longitude']);

            if (!$island) {
                return response()->json([
                    'success' => false,
                    'message' => 'Island not found'
                ], 404);
            }

            // Get island slug in current locale
            $islandSlug = $this->getIslandSlug($island);

            // Use ProvidesWeather trait to build response
            return $this->buildWeatherResponse($island, 'island', $islandSlug);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving weather data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get island slug in current locale
     */
    private function getIslandSlug($island): ?string
    {
        $locale = app()->getLocale();

        $translation = $island->translations->firstWhere('locale', $locale);
        if ($translation) {
            return $translation->slug;
        }

        // Fallback to English
        $translationEn = $island->translations->firstWhere('locale', 'en');
        return $translationEn?->slug;
    }
}

