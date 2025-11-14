<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NaturalLocationCondition;
use App\Http\Resources\NaturalLocationConditionResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class NaturalLocationConditionController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/natural-location-conditions",
     *     summary="Get all natural location conditions",
     *     description="Retrieve a list of all natural conditions at beach locations (sand types, stones, rocks, etc.) with their translations",
     *     operationId="getNaturalLocationConditions",
     *     tags={"Natural Location Conditions"},
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
     *                 @OA\Items(ref="#/components/schemas/NaturalLocationCondition")
     *             ),
     *             @OA\Property(property="message", type="string", example="Natural location conditions retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve natural location conditions")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "natural_location_conditions:index:{$locale}:all";

            $naturalLocationConditions = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return NaturalLocationCondition::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('beach.status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => NaturalLocationConditionResource::collection($naturalLocationConditions),
                'message' => 'Natural location conditions retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve natural location conditions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/natural-location-conditions/{id}",
     *     summary="Get a specific natural location condition",
     *     description="Retrieve a single natural condition by ID with all translations",
     *     operationId="getNaturalLocationConditionById",
     *     tags={"Natural Location Conditions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Condition ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/NaturalLocationCondition"),
     *             @OA\Property(property="message", type="string", example="Natural location condition retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Condition not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Natural location condition not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve natural location condition")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "natural_location_condition:show:{$locale}:{$id}";

            $naturalLocationCondition = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return NaturalLocationCondition::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('beach.status_id', 1);
                    }])
                ->find($id);
            });

            if (!$naturalLocationCondition) {
                return response()->json([
                    'success' => false,
                    'message' => 'Natural location condition not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new NaturalLocationConditionResource($naturalLocationCondition),
                'message' => 'Natural location condition retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve natural location condition: ' . $e->getMessage()
            ], 500);
        }
    }
}

