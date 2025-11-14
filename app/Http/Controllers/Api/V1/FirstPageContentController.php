<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FirstPageContentResource;
use App\Models\FirstPageContent;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="First Page Content",
 *     description="API Endpoints for first page/homepage content sections"
 * )
 */
class FirstPageContentController extends Controller
{
    use CacheAware;

    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/first-page-content",
     *     operationId="getFirstPageContent",
     *     summary="Get all first page content sections",
     *     description="Returns all active first page content sections with translations, items, and media",
     *     tags={"First Page Content"},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, sl, de, hr, etc.)",
     *         required=false,
     *         @OA\Schema(type="string", default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/FirstPageContent")
     *             ),
     *             @OA\Property(property="message", type="string", example="First page content retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "first_page_content:index:{$locale}";

            $content = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
                return FirstPageContent::with([
                    'translations',
                    'items.translations',
                    'media.mediaType',
                    'media.mediaCollection',
                    'items.media.mediaType',
                    'items.media.mediaCollection',
                ])
                    ->active()
                    ->ordered()
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => FirstPageContentResource::collection($content),
                'message' => 'First page content retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve first page content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/first-page-content/{code}",
     *     operationId="getFirstPageContentByCode",
     *     summary="Get specific first page content section by code",
     *     description="Returns a specific first page content section with translations, items, and media",
     *     tags={"First Page Content"},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Content section code",
     *         required=true,
     *         @OA\Schema(type="string", example="hero_banner")
     *     ),
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, sl, de, hr, etc.)",
     *         required=false,
     *         @OA\Schema(type="string", default="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/FirstPageContent"),
     *             @OA\Property(property="message", type="string", example="First page content retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Content not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function show(string $code): JsonResponse
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "first_page_content:show:{$code}:{$locale}";

            $content = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($code) {
                return FirstPageContent::with([
                    'translations',
                    'items.translations',
                    'media.mediaType',
                    'media.mediaCollection',
                    'items.media.mediaType',
                    'items.media.mediaCollection',
                ])
                    ->where('code', $code)
                    ->active()
                    ->first();
            });

            if (!$content) {
                return response()->json([
                    'success' => false,
                    'message' => 'First page content not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new FirstPageContentResource($content),
                'message' => 'First page content retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve first page content: ' . $e->getMessage()
            ], 500);
        }
    }
}

