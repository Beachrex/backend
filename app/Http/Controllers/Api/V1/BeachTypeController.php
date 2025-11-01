<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeachTypeResource;
use App\Models\BeachType;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

/**
 * @OA\Tag(
 *     name="Beach Types",
 *     description="API Endpoints for beach surface types (sand, stones, rock, etc.)"
 * )
 */
class BeachTypeController extends Controller
{
    use CacheAware;
    
    /**
     * @OA\Get(
     *     path="/api/v1/beach-types",
     *     operationId="getBeachTypes",
     *     summary="List all beach types",
     *     description="Get a list of all beach surface types with translations. Optionally filter by show_filter flag.",
     *     tags={"Beach Types"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Filter beach types shown in filters (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="include_translations",
     *         in="query",
     *         description="Include all available translations in the response (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of beach types retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/BeachType")
     *             ),
     *             @OA\Property(property="message", type="string", example="Beach types retrieved successfully")
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
            $showFilter = request()->has('filter') && request()->get('filter') === 'true';
            $cacheKey = "beach_types:index:{$locale}:" . ($showFilter ? 'filtered' : 'all');

            $beachTypes = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($showFilter) {
                $query = BeachType::with('translations')
                    ->withCount('beaches')
                    ->active()
                    ->orderBy('id');

                // Filter by show_filter if requested
                if ($showFilter) {
                    $query->showInFilter();
                }

                return $query->get();
            });

            return response()->json([
                'success' => true,
                'data' => BeachTypeResource::collection($beachTypes),
                'message' => 'Beach types retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving beach types',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beach-types/{id}",
     *     operationId="getBeachTypeById",
     *     summary="Get beach type by ID",
     *     description="Get a specific beach type by ID with all translations",
     *     tags={"Beach Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Beach Type ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="include_translations",
     *         in="query",
     *         description="Include all available translations in the response (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Beach type retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/BeachType"),
     *             @OA\Property(property="message", type="string", example="Beach type retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Beach type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Beach type not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "beach_type:show:{$locale}:{$id}";

            $beachType = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return BeachType::with('translations')
                    ->withCount('beaches')
                    ->find($id);
            });

            if (!$beachType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beach type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new BeachTypeResource($beachType),
                'message' => 'Beach type retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the beach type',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

