<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SportingActivities;
use App\Http\Resources\SportingActivitiesResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class SportingActivitiesController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/sporting-activities",
     *     summary="Get all sporting activities",
     *     description="Retrieve a list of all sporting activities available at beaches with their translations",
     *     operationId="getSportingActivities",
     *     tags={"Sporting Activities"},
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
     *                 @OA\Items(ref="#/components/schemas/SportingActivities")
     *             ),
     *             @OA\Property(property="message", type="string", example="Sporting activities retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve sporting activities")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "sporting_activities:index:{$locale}:all";

            $sportingActivities = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return SportingActivities::with('translations')
                    ->withCount('beaches')
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => SportingActivitiesResource::collection($sportingActivities),
                'message' => 'Sporting activities retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sporting activities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sporting-activities/{id}",
     *     summary="Get a specific sporting activity",
     *     description="Retrieve a single sporting activity by ID with all translations",
     *     operationId="getSportingActivityById",
     *     tags={"Sporting Activities"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Activity ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/SportingActivities"),
     *             @OA\Property(property="message", type="string", example="Sporting activity retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sporting activity not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve sporting activity")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "sporting_activitie:show:{$locale}:{$id}";

            $sportingActivitie = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return SportingActivities::with('translations')
                    ->withCount('beaches')
                ->find($id);
            });

            if (!$sportingActivitie) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sporting activity not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new SportingActivitiesResource($sportingActivitie),
                'message' => 'Sporting activity retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sporting activity: ' . $e->getMessage()
            ], 500);
        }
    }
}

