<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserType;
use App\Http\Resources\UserTypeResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class UserTypeController extends Controller
{
    use CacheAware;
    
    /**
     * @OA\Get(
     *     path="/api/v1/user-types",
     *     summary="Get all user types",
     *     description="Retrieve a list of all user types (adventurer, family, youth) with their translations",
     *     operationId="getUserTypes",
     *     tags={"User Types"},
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
     *                 @OA\Items(ref="#/components/schemas/UserType")
     *             ),
     *             @OA\Property(property="message", type="string", example="User types retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve user types")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "user_types:index:{$locale}:all";
            
            $userTypes = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return UserType::with('translations')
                    ->withCount('beaches')
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => UserTypeResource::collection($userTypes),
                'message' => 'User types retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user-types/{id}",
     *     summary="Get a specific user type",
     *     description="Retrieve a single user type by ID with all translations",
     *     operationId="getUserTypeById",
     *     tags={"User Types"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User type ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/UserType"),
     *             @OA\Property(property="message", type="string", example="User type retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve user type")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "user_type:show:{$locale}:{$id}";
            
            $userType = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return UserType::with('translations')
                    ->withCount('beaches')
                ->find($id);
            });

            if (!$userType) {
                return response()->json([
                    'success' => false,
                    'message' => 'User type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new UserTypeResource($userType),
                'message' => 'User type retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user type: ' . $e->getMessage()
            ], 500);
        }
    }
}

