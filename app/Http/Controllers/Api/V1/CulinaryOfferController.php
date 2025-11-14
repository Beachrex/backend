<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CulinaryOffer;
use App\Http\Resources\CulinaryOfferResource;
use Illuminate\Http\JsonResponse;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use App\Traits\CacheAware;

class CulinaryOfferController extends Controller
{
    use CacheAware;

    /**
     * @OA\Get(
     *     path="/api/v1/culinary-offers",
     *     summary="Get all culinary offers",
     *     description="Retrieve a list of all culinary offers available at beaches with their translations",
     *     operationId="getCulinaryOffers",
     *     tags={"Culinary Offers"},
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
     *                 @OA\Items(ref="#/components/schemas/CulinaryOffer")
     *             ),
     *             @OA\Property(property="message", type="string", example="Culinary offers retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve culinary offers")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "culinary_offers:index:{$locale}:all";

            $culinaryOffers = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return CulinaryOffer::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('beach.status_id', 1);
                    }])
                    ->active()
                    ->orderBy('id')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => CulinaryOfferResource::collection($culinaryOffers),
                'message' => 'Culinary offers retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve culinary offers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/culinary-offers/{id}",
     *     summary="Get a specific culinary offer",
     *     description="Retrieve a single culinary offer by ID with all translations",
     *     operationId="getCulinaryOfferById",
     *     tags={"Culinary Offers"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Culinary Offer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CulinaryOffer"),
     *             @OA\Property(property="message", type="string", example="Culinary offer retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Culinary offer not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Culinary offer not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve culinary offer")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "culinary_offer:show:{$locale}:{$id}";

            $culinaryOffer = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($id) {
                return CulinaryOffer::with('translations')
                    ->withCount(['beaches' => function ($q) {
                        $q->where('beach.status_id', 1);
                    }])
                ->find($id);
            });

            if (!$culinaryOffer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Culinary offer not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new CulinaryOfferResource($culinaryOffer),
                'message' => 'Culinary offer retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve culinary offer: ' . $e->getMessage()
            ], 500);
        }
    }
}

