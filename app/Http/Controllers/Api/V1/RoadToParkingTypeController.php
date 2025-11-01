<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RoadToParkingType;
use App\Http\Resources\RoadToParkingTypeResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Traits\CacheAware;

class RoadToParkingTypeController extends Controller
{
    use CacheAware;
    
    /**
     * @OA\Get(
     *     path="/api/v1/road-to-parking-types",
     *     summary="Get all road to parking types",
     *     description="Retrieve a list of all road to parking types with their translations",
     *     operationId="getRoadToParkingTypes",
     *     tags={"Road to Parking Types"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="include_translations",
     *         in="query",
     *         description="Include all available translations in the response (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Filter to show only types visible in filters (show_filter=true)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/RoadToParkingType")
     *             ),
     *             @OA\Property(property="message", type="string", example="Road to parking types retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve road to parking types")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $showFilter = request()->has('filter') && request()->get('filter') === 'true';
            $cacheKey = "road_to_parking_types:index:{$locale}:" . ($showFilter ? 'filtered' : 'all');
            
            $roadToParkingTypes = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($showFilter) {
                $query = RoadToParkingType::with('translations')
                ->withCount('beaches')
                    ->active()
                    ->orderBy('id');

            // Apply filter if requested
            if ($request->query('filter') === 'true') {
                $query->showInFilter();
            }

            return $roadToParkingTypes = $query->get();
            });

            return response()->json([
                'success' => true,
                'data' => RoadToParkingTypeResource::collection($roadToParkingTypes),
                'message' => 'Road to parking types retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve road to parking types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/road-to-parking-types/{id}",
     *     summary="Get a specific road to parking type",
     *     description="Retrieve a single road to parking type by ID with all translations",
     *     operationId="getRoadToParkingTypeById",
     *     tags={"Road to Parking Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Road to Parking Type ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/RoadToParkingType"),
     *             @OA\Property(property="message", type="string", example="Road to parking type retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Road to parking type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Road to parking type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve road to parking type")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "road_to_parking_type:show:{$locale}:{$id}";
            
            $roadToParkingType = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return RoadToParkingType::with('translations')
                    ->withCount('beaches')
                ->find($id);
            });

            if (!$roadToParkingType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Road to parking type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new RoadToParkingTypeResource($roadToParkingType),
                'message' => 'Road to parking type retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve road to parking type: ' . $e->getMessage()
            ], 500);
        }
    }
}

