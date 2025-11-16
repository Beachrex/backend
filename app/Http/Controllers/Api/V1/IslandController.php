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

/**
 * @OA\Tag(
 *     name="Islands",
 *     description="API Endpoints for islands"
 * )
 */
class IslandController extends Controller
{
    use CacheAware;

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
                        $q->where('slug', $slug)->where('locale', $locale);
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
}

