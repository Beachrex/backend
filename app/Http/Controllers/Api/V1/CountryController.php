<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Countries",
 *     description="API Endpoints for countries"
 * )
 */
class CountryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/countries",
     *     operationId="getCountries",
     *     tags={"Countries"},
     *     summary="Get all countries that have beaches",
     *     description="Returns a list of countries that have at least one beach. Can be filtered by continent.",
     *     @OA\Parameter(
     *         name="continent",
     *         in="query",
     *         description="Filter by continent code (e.g., EU, AS, NA)",
     *         required=false,
     *         @OA\Schema(type="string", example="EU")
     *     ),
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
     *                     @OA\Property(property="code", type="string", example="SI"),
     *                     @OA\Property(property="name", type="string", example="Slovenia"),
     *                     @OA\Property(property="full_name", type="string", example="Republic of Slovenia"),
     *                     @OA\Property(property="iso3", type="string", example="SVN"),
     *                     @OA\Property(property="continent_code", type="string", example="EU"),
     *                     @OA\Property(property="beaches_count", type="integer", example=5)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Countries retrieved successfully"
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
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Country::withBeaches()
                ->withCount('beaches')
                ->orderBy('name');

            // Filter by continent if provided
            if ($request->has('continent')) {
                $query->byContinent($request->get('continent'));
            }

            $countries = $query->get([
                'code', 
                'name', 
                'full_name', 
                'iso3', 
                'continent_code'
            ]);

            return response()->json([
                'success' => true,
                'data' => CountryResource::collection($countries),
                'message' => 'Countries retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving countries',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/countries/{code}",
     *     operationId="getCountry",
     *     tags={"Countries"},
     *     summary="Get a specific country",
     *     description="Returns a specific country by code with its beaches count and continent information",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Country code (e.g., SI, US, DE)",
     *         required=true,
     *         @OA\Schema(type="string", example="SI")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="code", type="string", example="SI"),
     *                 @OA\Property(property="name", type="string", example="Slovenia"),
     *                 @OA\Property(property="full_name", type="string", example="Republic of Slovenia"),
     *                 @OA\Property(property="iso3", type="string", example="SVN"),
     *                 @OA\Property(property="continent_code", type="string", example="EU"),
     *                 @OA\Property(property="beaches_count", type="integer", example=5),
     *                 @OA\Property(
     *                     property="continent",
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="EU"),
     *                     @OA\Property(property="name", type="string", example="Europe")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="Country retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Country not found")
     *         )
     *     )
     * )
     */
    public function show(string $code): JsonResponse
    {
        try {
            $country = Country::with('continent')
                ->withCount('beaches')
                ->where('code', strtoupper($code))
                ->first([
                    'code', 
                    'name', 
                    'full_name', 
                    'iso3', 
                    'continent_code'
                ]);

            if (!$country) {
                return response()->json([
                    'success' => false,
                    'message' => 'Country not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new CountryResource($country),
                'message' => 'Country retrieved successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the country',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
