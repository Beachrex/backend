<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LocationLength;
use App\Http\Resources\LocationLengthResource;
use Illuminate\Http\JsonResponse;

class LocationLengthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/location-lengths",
     *     summary="Get all location lengths",
     *     description="Retrieve a list of all location lengths with their translations",
     *     operationId="getLocationLengths",
     *     tags={"Location Lengths"},
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
     *                 @OA\Items(ref="#/components/schemas/LocationLength")
     *             ),
     *             @OA\Property(property="message", type="string", example="Location lengths retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve location lengths")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locationLengths = LocationLength::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => LocationLengthResource::collection($locationLengths),
                'message' => 'Location lengths retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve location lengths: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/location-lengths/{id}",
     *     summary="Get a specific location length",
     *     description="Retrieve a single location length by ID with all translations",
     *     operationId="getLocationLengthById",
     *     tags={"Location Lengths"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Location Length ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/LocationLength"),
     *             @OA\Property(property="message", type="string", example="Location length retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Location length not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Location length not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve location length")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locationLength = LocationLength::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$locationLength) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location length not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new LocationLengthResource($locationLength),
                'message' => 'Location length retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve location length: ' . $e->getMessage()
            ], 500);
        }
    }
}

