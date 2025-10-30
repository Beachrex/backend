<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaterbodyResource;
use App\Models\Waterbody;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Waterbodies",
 *     description="API Endpoints for waterbody types (sea, lake, river, pool)"
 * )
 */
class WaterbodyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/waterbodies",
     *     operationId="getWaterbodies",
     *     summary="List all waterbody types",
     *     description="Get a list of all waterbody types with translations. Optionally filter by show_filter flag.",
     *     tags={"Waterbodies"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Filter waterbodies shown in filters (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="include_translations",
     *         in="query",
     *         description="Include all available translations in the response (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of waterbodies retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Waterbody")
     *             ),
     *             @OA\Property(property="message", type="string", example="Waterbodies retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $query = Waterbody::with('translations')
                ->withCount('beaches')
                ->active()
                ->orderBy('id');

            // Filter by show_filter if requested
            if (request()->has('filter') && request()->get('filter') === 'true') {
                $query->showInFilter();
            }

            $waterbodies = $query->get();

            return response()->json([
                'success' => true,
                'data' => WaterbodyResource::collection($waterbodies),
                'message' => 'Waterbodies retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving waterbodies',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/waterbodies/{id}",
     *     operationId="getWaterbodyById",
     *     summary="Get waterbody by ID",
     *     description="Get a specific waterbody type by ID with all translations",
     *     tags={"Waterbodies"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Waterbody ID",
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
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Waterbody retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Waterbody"),
     *             @OA\Property(property="message", type="string", example="Waterbody retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Waterbody not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Waterbody not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $waterbody = Waterbody::with('translations')
                ->withCount('beaches')
                ->find($id);

            if (!$waterbody) {
                return response()->json([
                    'success' => false,
                    'message' => 'Waterbody not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new WaterbodyResource($waterbody),
                'message' => 'Waterbody retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the waterbody',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

