<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocationInfrastructure;
use App\Http\Resources\LocationInfrastructureResource;
use Illuminate\Http\JsonResponse;

class LocationInfrastructureController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/location-infrastructure",
     *     summary="Get all location infrastructure types",
     *     description="Retrieve a list of all infrastructure types available at beach locations with their translations",
     *     operationId="getLocationInfrastructure",
     *     tags={"Location Infrastructure"},
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
     *                 @OA\Items(ref="#/components/schemas/LocationInfrastructure")
     *             ),
     *             @OA\Property(property="message", type="string", example="Location infrastructure retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve location infrastructure")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $infrastructure = LocationInfrastructure::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => LocationInfrastructureResource::collection($infrastructure),
                'message' => 'Location infrastructure retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve location infrastructure: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/location-infrastructure/{id}",
     *     summary="Get a specific location infrastructure type",
     *     description="Retrieve a single infrastructure type by ID with all translations",
     *     operationId="getLocationInfrastructureById",
     *     tags={"Location Infrastructure"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Infrastructure ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/LocationInfrastructure"),
     *             @OA\Property(property="message", type="string", example="Location infrastructure retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Infrastructure not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Location infrastructure not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve location infrastructure")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $infrastructure = LocationInfrastructure::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$infrastructure) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location infrastructure not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new LocationInfrastructureResource($infrastructure),
                'message' => 'Location infrastructure retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve location infrastructure: ' . $e->getMessage()
            ], 500);
        }
    }
}

