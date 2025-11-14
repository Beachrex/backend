<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BeachInclination;
use App\Http\Resources\BeachInclinationResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class BeachInclinationController extends Controller
{
    use CacheAware;
    
    /**
     * @OA\Get(
     *     path="/api/v1/beach-inclinations",
     *     summary="Get all beach inclinations",
     *     description="Retrieve a list of all beach inclinations with their translations",
     *     operationId="getBeachInclinations",
     *     tags={"Beach Inclinations"},
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
     *                 @OA\Items(ref="#/components/schemas/BeachInclination")
     *             ),
     *             @OA\Property(property="message", type="string", example="Beach inclinations retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve beach inclinations")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "beach_inclinations:index:{$locale}:all";
            
            $beachInclinations = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return BeachInclination::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => BeachInclinationResource::collection($beachInclinations),
                'message' => 'Beach inclinations retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve beach inclinations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/beach-inclinations/{id}",
     *     summary="Get a specific beach inclination",
     *     description="Retrieve a single beach inclination by ID with all translations",
     *     operationId="getBeachInclinationById",
     *     tags={"Beach Inclinations"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Beach Inclination ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/BeachInclination"),
     *             @OA\Property(property="message", type="string", example="Beach inclination retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Beach inclination not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Beach inclination not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve beach inclination")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "beach_inclination:show:{$locale}:{$id}";
            
            $beachInclination = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return BeachInclination::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('status_id', 1);
                    }])
                ->find($id);
            });

            if (!$beachInclination) {
                return response()->json([
                    'success' => false,
                    'message' => 'Beach inclination not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new BeachInclinationResource($beachInclination),
                'message' => 'Beach inclination retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve beach inclination: ' . $e->getMessage()
            ], 500);
        }
    }
}

