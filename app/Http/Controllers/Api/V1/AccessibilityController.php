<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Accessibility;
use App\Http\Resources\AccessibilityResource;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class AccessibilityController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/accessibilities",
     *     summary="Get all accessibility options",
     *     description="Retrieve a list of all accessibility options with their translations",
     *     operationId="getAccessibilities",
     *     tags={"Accessibility"},
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
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Accessibility")
     *             ),
     *             @OA\Property(property="message", type="string", example="Accessibility options retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve accessibility options")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "accessibilities:index:{$locale}:all";

            $accessibilities = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return Accessibility::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => AccessibilityResource::collection($accessibilities),
                'message' => 'Accessibility options retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accessibility options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/accessibilities/{id}",
     *     summary="Get a specific accessibility option",
     *     description="Retrieve a single accessibility option by ID with all translations",
     *     operationId="getAccessibilityById",
     *     tags={"Accessibility"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Accessibility ID",
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
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Accessibility"),
     *             @OA\Property(property="message", type="string", example="Accessibility option retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Accessibility option not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Accessibility option not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve accessibility option")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "accessibility:show:{$locale}:{$id}";

            $accessibility = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return Accessibility::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->find($id);
            });

            if (!$accessibility) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accessibility option not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new AccessibilityResource($accessibility),
                'message' => 'Accessibility option retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accessibility option: ' . $e->getMessage()
            ], 500);
        }
    }
}

