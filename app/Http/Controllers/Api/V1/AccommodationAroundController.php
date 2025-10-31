<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccommodationAround;
use App\Http\Resources\AccommodationAroundResource;
use Illuminate\Http\JsonResponse;

class AccommodationAroundController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/accommodation-around",
     *     summary="Get all accommodation types",
     *     description="Retrieve a list of all accommodation types available around beaches with their translations",
     *     operationId="getAccommodationTypes",
     *     tags={"Accommodation Around"},
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
     *                 @OA\Items(ref="#/components/schemas/AccommodationAround")
     *             ),
     *             @OA\Property(property="message", type="string", example="Accommodation types retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve accommodation types")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $accommodationTypes = AccommodationAround::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => AccommodationAroundResource::collection($accommodationTypes),
                'message' => 'Accommodation types retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accommodation types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/accommodation-around/{id}",
     *     summary="Get a specific accommodation type",
     *     description="Retrieve a single accommodation type by ID with all translations",
     *     operationId="getAccommodationTypeById",
     *     tags={"Accommodation Around"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Accommodation Type ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/AccommodationAround"),
     *             @OA\Property(property="message", type="string", example="Accommodation type retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Accommodation type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Accommodation type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve accommodation type")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $accommodationType = AccommodationAround::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$accommodationType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accommodation type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new AccommodationAroundResource($accommodationType),
                'message' => 'Accommodation type retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve accommodation type: ' . $e->getMessage()
            ], 500);
        }
    }
}

