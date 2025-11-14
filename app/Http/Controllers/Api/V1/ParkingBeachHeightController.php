<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ParkingBeachHeight;
use App\Http\Resources\ParkingBeachHeightResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class ParkingBeachHeightController extends Controller
{
    use CacheAware;
    
    /**
     * @OA\Get(
     *     path="/api/v1/parking-beach-heights",
     *     summary="Get all parking beach heights",
     *     description="Retrieve a list of all parking beach heights with their translations",
     *     operationId="getParkingBeachHeights",
     *     tags={"Parking Beach Heights"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="include_translations",
     *         in="query",
     *         description="Include all available translations in the response (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ParkingBeachHeight")
     *             ),
     *             @OA\Property(property="message", type="string", example="Parking beach heights retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve parking beach heights")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "parking_beach_heights:index:{$locale}:all";
            
            $parkingBeachHeights = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return ParkingBeachHeight::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => ParkingBeachHeightResource::collection($parkingBeachHeights),
                'message' => 'Parking beach heights retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parking beach heights: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/parking-beach-heights/{id}",
     *     summary="Get a specific parking beach height",
     *     description="Retrieve a single parking beach height by ID with all translations",
     *     operationId="getParkingBeachHeightById",
     *     tags={"Parking Beach Heights"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parking Beach Height ID",
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
     *     ),     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/ParkingBeachHeight"),
     *             @OA\Property(property="message", type="string", example="Parking beach height retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parking beach height not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Parking beach height not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve parking beach height")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "parking_beach_height:show:{$locale}:{$id}";
            
            $parkingBeachHeight = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return ParkingBeachHeight::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                ->find($id);
            });

            if (!$parkingBeachHeight) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parking beach height not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ParkingBeachHeightResource($parkingBeachHeight),
                'message' => 'Parking beach height retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parking beach height: ' . $e->getMessage()
            ], 500);
        }
    }
}

