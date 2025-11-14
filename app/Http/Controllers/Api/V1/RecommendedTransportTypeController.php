<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RecommendedTransportType;
use App\Http\Resources\RecommendedTransportTypeResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class RecommendedTransportTypeController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/recommended-transport-types",
     *     summary="Get all recommended transport types",
     *     description="Retrieve a list of all recommended transport types for reaching beaches with their translations",
     *     operationId="getRecommendedTransportTypes",
     *     tags={"Recommended Transport Types"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/RecommendedTransportType")
     *             ),
     *             @OA\Property(property="message", type="string", example="Recommended transport types retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve recommended transport types")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "recommended_transport_types:index:{$locale}:all";

            $recommendedTransportTypes = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return RecommendedTransportType::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('beach.status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => RecommendedTransportTypeResource::collection($recommendedTransportTypes),
                'message' => 'Recommended transport types retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recommended transport types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/recommended-transport-types/{id}",
     *     summary="Get a specific recommended transport type",
     *     description="Retrieve a single transport type by ID with all translations",
     *     operationId="getRecommendedTransportTypeById",
     *     tags={"Recommended Transport Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transport type ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/RecommendedTransportType"),
     *             @OA\Property(property="message", type="string", example="Recommended transport type retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transport type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Recommended transport type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve recommended transport type")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "recommended_transport_type:show:{$locale}:{$id}";

            $recommendedTransportType = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return RecommendedTransportType::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('beach.status_id', 1);
                    }])
                ->find($id);
            });

            if (!$recommendedTransportType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recommended transport type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new RecommendedTransportTypeResource($recommendedTransportType),
                'message' => 'Recommended transport type retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recommended transport type: ' . $e->getMessage()
            ], 500);
        }
    }
}

