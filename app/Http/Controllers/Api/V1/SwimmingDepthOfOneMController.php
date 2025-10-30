<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SwimmingDepthOfOneM;
use App\Http\Resources\SwimmingDepthOfOneMResource;
use Illuminate\Http\JsonResponse;

class SwimmingDepthOfOneMController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/swimming-depths",
     *     summary="Get all swimming depths of 1m",
     *     description="Retrieve a list of all swimming depths (distance to reach 1m depth) with their translations",
     *     operationId="getSwimmingDepths",
     *     tags={"Swimming Depths"},
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
     *                 @OA\Items(ref="#/components/schemas/SwimmingDepthOfOneM")
     *             ),
     *             @OA\Property(property="message", type="string", example="Swimming depths retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve swimming depths")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $swimmingDepths = SwimmingDepthOfOneM::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => SwimmingDepthOfOneMResource::collection($swimmingDepths),
                'message' => 'Swimming depths retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve swimming depths: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/swimming-depths/{id}",
     *     summary="Get a specific swimming depth",
     *     description="Retrieve a single swimming depth by ID with all translations",
     *     operationId="getSwimmingDepthById",
     *     tags={"Swimming Depths"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Swimming Depth ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/SwimmingDepthOfOneM"),
     *             @OA\Property(property="message", type="string", example="Swimming depth retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Swimming depth not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Swimming depth not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve swimming depth")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $swimmingDepth = SwimmingDepthOfOneM::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$swimmingDepth) {
                return response()->json([
                    'success' => false,
                    'message' => 'Swimming depth not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new SwimmingDepthOfOneMResource($swimmingDepth),
                'message' => 'Swimming depth retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve swimming depth: ' . $e->getMessage()
            ], 500);
        }
    }
}

