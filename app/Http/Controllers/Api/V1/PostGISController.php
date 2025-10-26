<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PostGISService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Spatial",
 *     description="PostGIS spatial operations for beaches"
 * )
 */
class PostGISController extends Controller
{
    protected PostGISService $postGISService;

    public function __construct(PostGISService $postGISService)
    {
        $this->postGISService = $postGISService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/within-radius",
     *     operationId="getBeachesWithinRadius",
     *     summary="Find beaches within radius",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Beaches found within radius")
     * )
     */
    public function beachesWithinRadius(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:0.1|max:20000',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        $beaches = $this->postGISService->getBeachesWithinRadius(
            $request->latitude,
            $request->longitude,
            $request->radius,
            $request->get('limit', 50)
        );

        return response()->json([
            'data' => $beaches,
            'meta' => [
                'center' => ['latitude' => $request->latitude, 'longitude' => $request->longitude],
                'radius_km' => $request->radius,
                'count' => $beaches->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/nearest",
     *     operationId="getNearestBeaches",
     *     summary="Find nearest beaches",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Nearest beaches found")
     * )
     */
    public function nearestBeaches(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $beaches = $this->postGISService->getNearestBeaches(
            $request->latitude,
            $request->longitude,
            $request->get('limit', 10)
        );

        return response()->json([
            'data' => $beaches,
            'meta' => [
                'reference_point' => ['latitude' => $request->latitude, 'longitude' => $request->longitude],
                'limit' => $request->get('limit', 10),
                'count' => $beaches->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/distance/calculate",
     *     operationId="calculateDistance",
     *     summary="Calculate distance between two points",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Distance calculation result")
     * )
     */
    public function calculateDistance(Request $request): JsonResponse
    {
        $request->validate([
            'lat1' => 'required|numeric|between:-90,90',
            'lon1' => 'required|numeric|between:-180,180',
            'lat2' => 'required|numeric|between:-90,90',
            'lon2' => 'required|numeric|between:-180,180'
        ]);

        $distance = $this->postGISService->calculateDistance(
            $request->lat1,
            $request->lon1,
            $request->lat2,
            $request->lon2
        );

        return response()->json([
            'data' => [
                'distance_km' => round($distance, 2),
                'distance_miles' => round($distance * 0.621371, 2)
            ],
            'meta' => [
                'point1' => ['latitude' => $request->lat1, 'longitude' => $request->lon1],
                'point2' => ['latitude' => $request->lat2, 'longitude' => $request->lon2]
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/bounding-box",
     *     operationId="getBeachesInBoundingBox",
     *     summary="Get beaches within bounding box",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Beaches found within bounding box")
     * )
     */
    public function beachesInBoundingBox(Request $request): JsonResponse
    {
        $request->validate([
            'min_lat' => 'required|numeric|between:-90,90',
            'min_lon' => 'required|numeric|between:-180,180',
            'max_lat' => 'required|numeric|between:-90,90',
            'max_lon' => 'required|numeric|between:-180,180'
        ]);

        $beaches = $this->postGISService->getBeachesInBoundingBox(
            $request->min_lat,
            $request->min_lon,
            $request->max_lat,
            $request->max_lon
        );

        return response()->json([
            'data' => $beaches,
            'meta' => [
                'bounding_box' => [
                    'min_latitude' => $request->min_lat,
                    'min_longitude' => $request->min_lon,
                    'max_latitude' => $request->max_lat,
                    'max_longitude' => $request->max_lon
                ],
                'count' => $beaches->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/density-grid",
     *     operationId="getBeachDensityGrid",
     *     summary="Get beach density grid",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Beach density grid data")
     * )
     */
    public function beachDensityGrid(Request $request): JsonResponse
    {
        $request->validate([
            'grid_size' => 'sometimes|numeric|min:0.1|max:10'
        ]);

        $density = $this->postGISService->getBeachDensityGrid(
            $request->get('grid_size', 1.0)
        );

        return response()->json([
            'data' => $density,
            'meta' => [
                'grid_size_degrees' => $request->get('grid_size', 1.0),
                'grid_size_km_approx' => round($request->get('grid_size', 1.0) * 111, 1),
                'count' => $density->count()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spatial/beaches/statistics",
     *     operationId="getBeachStatistics",
     *     summary="Get beach statistics",
     *     tags={"Spatial"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(response=200, description="Beach statistics")
     * )
     */
    public function beachStatistics(): JsonResponse
    {
        $stats = $this->postGISService->getBeachStatistics();

        return response()->json([
            'data' => $stats
        ]);
    }
}
