<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AreaDescription;
use App\Http\Resources\AreaDescriptionResource;
use Illuminate\Http\JsonResponse;

class AreaDescriptionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/area-descriptions",
     *     summary="Get all area descriptions",
     *     description="Retrieve a list of all area descriptions with their translations",
     *     operationId="getAreaDescriptions",
     *     tags={"Area Descriptions"},
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
     *                 @OA\Items(ref="#/components/schemas/AreaDescription")
     *             ),
     *             @OA\Property(property="message", type="string", example="Area descriptions retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve area descriptions")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $areaDescriptions = AreaDescription::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => AreaDescriptionResource::collection($areaDescriptions),
                'message' => 'Area descriptions retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve area descriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/area-descriptions/{id}",
     *     summary="Get a specific area description",
     *     description="Retrieve a single area description by ID with all translations",
     *     operationId="getAreaDescriptionById",
     *     tags={"Area Descriptions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Area Description ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/AreaDescription"),
     *             @OA\Property(property="message", type="string", example="Area description retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Area description not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Area description not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve area description")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $areaDescription = AreaDescription::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$areaDescription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Area description not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new AreaDescriptionResource($areaDescription),
                'message' => 'Area description retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve area description: ' . $e->getMessage()
            ], 500);
        }
    }
}

