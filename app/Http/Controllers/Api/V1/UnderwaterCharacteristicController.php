<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UnderwaterCharacteristic;
use App\Http\Resources\UnderwaterCharacteristicResource;
use Illuminate\Http\JsonResponse;

class UnderwaterCharacteristicController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/underwater-characteristics",
     *     summary="Get all underwater characteristics",
     *     description="Retrieve a list of all underwater characteristics (sand, stones, rocks, etc.) with their translations",
     *     operationId="getUnderwaterCharacteristics",
     *     tags={"Underwater Characteristics"},
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
     *                 @OA\Items(ref="#/components/schemas/UnderwaterCharacteristic")
     *             ),
     *             @OA\Property(property="message", type="string", example="Underwater characteristics retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve underwater characteristics")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $characteristics = UnderwaterCharacteristic::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => UnderwaterCharacteristicResource::collection($characteristics),
                'message' => 'Underwater characteristics retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve underwater characteristics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/underwater-characteristics/{id}",
     *     summary="Get a specific underwater characteristic",
     *     description="Retrieve a single underwater characteristic by ID with all translations",
     *     operationId="getUnderwaterCharacteristicById",
     *     tags={"Underwater Characteristics"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Characteristic ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/UnderwaterCharacteristic"),
     *             @OA\Property(property="message", type="string", example="Underwater characteristic retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Characteristic not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Underwater characteristic not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve underwater characteristic")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $characteristic = UnderwaterCharacteristic::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$characteristic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Underwater characteristic not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new UnderwaterCharacteristicResource($characteristic),
                'message' => 'Underwater characteristic retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve underwater characteristic: ' . $e->getMessage()
            ], 500);
        }
    }
}

