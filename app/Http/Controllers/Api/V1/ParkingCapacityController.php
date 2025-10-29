<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ParkingCapacity;
use App\Http\Resources\ParkingCapacityResource;
use Illuminate\Http\JsonResponse;

class ParkingCapacityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/parking-capacities",
     *     summary="Get all parking capacities",
     *     description="Retrieve a list of all parking capacities with their translations",
     *     operationId="getParkingCapacities",
     *     tags={"Parking Capacities"},
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
     *                 @OA\Items(ref="#/components/schemas/ParkingCapacity")
     *             ),
     *             @OA\Property(property="message", type="string", example="Parking capacities retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve parking capacities")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $parkingCapacities = ParkingCapacity::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ParkingCapacityResource::collection($parkingCapacities),
                'message' => 'Parking capacities retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parking capacities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/parking-capacities/{id}",
     *     summary="Get a specific parking capacity",
     *     description="Retrieve a single parking capacity by ID with all translations",
     *     operationId="getParkingCapacityById",
     *     tags={"Parking Capacities"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parking Capacity ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/ParkingCapacity"),
     *             @OA\Property(property="message", type="string", example="Parking capacity retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parking capacity not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Parking capacity not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve parking capacity")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $parkingCapacity = ParkingCapacity::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$parkingCapacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parking capacity not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ParkingCapacityResource($parkingCapacity),
                'message' => 'Parking capacity retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parking capacity: ' . $e->getMessage()
            ], 500);
        }
    }
}

