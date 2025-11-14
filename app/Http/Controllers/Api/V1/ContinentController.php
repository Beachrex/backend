<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContinentResource;
use App\Models\Continent;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

/**
 * @OA\Tag(
 *     name="Continents",
 *     description="API Endpoints for continents"
 * )
 */
class ContinentController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/continents",
     *     operationId="getContinents",
     *     tags={"Continents"},
     *     summary="Get all continents that have beaches",
     *     description="Returns a list of continents that have at least one beach",
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="EU"),
     *                     @OA\Property(property="name", type="string", example="Europe"),
     *                     @OA\Property(property="slug", type="string", example="europe"),
     *                     @OA\Property(property="beaches_count", type="integer", example=15)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Continents retrieved successfully"
     *             )
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
            $locale = app()->getLocale();
            $cacheKey = 'continents:index';

            // Get only continents that have beaches, with beaches count
            $continents = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return Continent::withBeaches()
                    ->with('translations')
                    ->withCount('beaches')
                    ->get(['code']);
            });

            return response()->json([
                'success' => true,
                'data' => ContinentResource::collection($continents),
                'message' => 'Continents retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving continents',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/continents/{code}",
     *     operationId="getContinent",
     *     tags={"Continents"},
     *     summary="Get a specific continent",
     *     description="Returns a specific continent by code with its beaches count",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Continent code (e.g., EU, AS, NA)",
     *         required=true,
     *         @OA\Schema(type="string", example="EU")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="EU"),
     *                 @OA\Property(property="name", type="string", example="Europe"),
     *                 @OA\Property(property="slug", type="string", example="europe"),
     *                 @OA\Property(property="beaches_count", type="integer", example=15)
     *             ),
     *             @OA\Property(property="message", type="string", example="Continent retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Continent not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Continent not found")
     *         )
     *     )
     * )
     */
    public function show(string $code): JsonResponse
    {
        try {
            $code = strtoupper($code);
            $locale = app()->getLocale();
            $cacheKey = "continent:show:{$locale}:{$code}";

            $continent = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($code) {
                return Continent::with('translations')
                    ->withCount('beaches')
                    ->where('code', $code)
                    ->first(['code']);
            });

            if (!$continent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Continent not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ContinentResource($continent),
                'message' => 'Continent retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the continent',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
