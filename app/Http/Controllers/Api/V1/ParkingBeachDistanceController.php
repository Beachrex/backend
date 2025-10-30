<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ParkingBeachDistance;
use App\Http\Resources\ParkingBeachDistanceResource;
use Illuminate\Http\JsonResponse;

class ParkingBeachDistanceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/parking-beach-distances",
     *     summary="Get all parking beach distances",
     *     description="Retrieve a list of all parking beach distances with their translations",
     *     operationId="getParkingBeachDistances",
     *     tags={"Parking Beach Distances"},
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
     *                 @OA\Items(ref="#/components/schemas/ParkingBeachDistance")
     *             ),
     *             @OA\Property(property="message", type="string", example="Parking beach distances retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve parking beach distances")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $parkingBeachDistances = ParkingBeachDistance::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ParkingBeachDistanceResource::collection($parkingBeachDistances),
                'message' => 'Parking beach distances retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parking beach distances: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/parking-beach-distances/{id}",
     *     summary="Get a specific parking beach distance",
     *     description="Retrieve a single parking beach distance by ID with all translations",
     *     operationId="getParkingBeachDistanceById",
     *     tags={"Parking Beach Distances"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parking Beach Distance ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/ParkingBeachDistance"),
     *             @OA\Property(property="message", type="string", example="Parking beach distance retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parking beach distance not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Parking beach distance not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve parking beach distance")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $parkingBeachDistance = ParkingBeachDistance::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$parkingBeachDistance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parking beach distance not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ParkingBeachDistanceResource($parkingBeachDistance),
                'message' => 'Parking beach distance retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve parking beach distance: ' . $e->getMessage()
            ], 500);
        }
    }
}

