<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OccupancyRate;
use App\Http\Resources\OccupancyRateResource;
use Illuminate\Http\JsonResponse;

class OccupancyRateController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/occupancy-rates",
     *     summary="Get all occupancy rates",
     *     description="Retrieve a list of all occupancy rates with their translations",
     *     operationId="getOccupancyRates",
     *     tags={"Occupancy Rates"},
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
     *                 @OA\Items(ref="#/components/schemas/OccupancyRate")
     *             ),
     *             @OA\Property(property="message", type="string", example="Occupancy rates retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve occupancy rates")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $occupancyRates = OccupancyRate::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => OccupancyRateResource::collection($occupancyRates),
                'message' => 'Occupancy rates retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve occupancy rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/occupancy-rates/{id}",
     *     summary="Get a specific occupancy rate",
     *     description="Retrieve a single occupancy rate by ID with all translations",
     *     operationId="getOccupancyRateById",
     *     tags={"Occupancy Rates"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Occupancy Rate ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/OccupancyRate"),
     *             @OA\Property(property="message", type="string", example="Occupancy rate retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Occupancy rate not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Occupancy rate not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve occupancy rate")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $occupancyRate = OccupancyRate::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$occupancyRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Occupancy rate not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new OccupancyRateResource($occupancyRate),
                'message' => 'Occupancy rate retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve occupancy rate: ' . $e->getMessage()
            ], 500);
        }
    }
}

