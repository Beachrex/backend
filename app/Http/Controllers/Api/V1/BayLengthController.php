<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BayLength;
use App\Http\Resources\BayLengthResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class BayLengthController extends Controller
{
    use CacheAware;
    
    /**
     * @OA\Get(
     *     path="/api/v1/bay-lengths",
     *     summary="Get all bay lengths",
     *     description="Retrieve a list of all bay lengths with their translations",
     *     operationId="getBayLengths",
     *     tags={"Bay Lengths"},
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
     *                 @OA\Items(ref="#/components/schemas/BayLength")
     *             ),
     *             @OA\Property(property="message", type="string", example="Bay lengths retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve bay lengths")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "bay_lengths:index:{$locale}:all";
            
            $bayLengths = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return BayLength::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => BayLengthResource::collection($bayLengths),
                'message' => 'Bay lengths retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bay lengths: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/bay-lengths/{id}",
     *     summary="Get a specific bay length",
     *     description="Retrieve a single bay length by ID with all translations",
     *     operationId="getBayLengthById",
     *     tags={"Bay Lengths"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Bay Length ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/BayLength"),
     *             @OA\Property(property="message", type="string", example="Bay length retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Bay length not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bay length not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve bay length")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "bay_length:show:{$locale}:{$id}";
            
            $bayLength = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return BayLength::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                ->find($id);
            });

            if (!$bayLength) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bay length not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new BayLengthResource($bayLength),
                'message' => 'Bay length retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bay length: ' . $e->getMessage()
            ], 500);
        }
    }
}

