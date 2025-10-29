<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ParkingBeachHeight;
use App\Http\Resources\ParkingBeachHeightResource;
use Illuminate\Http\JsonResponse;

class ParkingBeachHeightController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/parking-beach-heights",
     *     summary="Get all parking beach heights",
     *     description="Retrieve a list of all parking beach heights with their translations",
     *     operationId="getParkingBeachHeights",
     *     tags={"Parking Beach Heights"},
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
            $parkingBeachHeights = ParkingBeachHeight::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

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
     *     @OA\Response(
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
            $parkingBeachHeight = ParkingBeachHeight::with('translations')
                ->withCount('beaches')
                ->find($id);

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

