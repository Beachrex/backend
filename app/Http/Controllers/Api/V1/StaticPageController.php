<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaticPageFooterResource;
use App\Http\Resources\StaticPageResource;
use App\Models\StaticPage;
use App\Services\CacheService;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Static Pages",
 *     description="API Endpoints for CMS-managed static pages (footer links and full page content)"
 * )
 */
class StaticPageController extends Controller
{
    use CacheAware;

    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/static-pages",
     *     operationId="getStaticPagesForFooter",
     *     summary="List active static pages for footer rendering",
     *     description="Returns lightweight list (id, code, title, slug, sort_order) of active pages flagged is_in_footer, localized by Accept-Language with English fallback.",
     *     tags={"Static Pages"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Footer pages retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/StaticPageFooter")),
     *             @OA\Property(property="message", type="string", example="Static pages retrieved successfully")
     *         ),
     *         @OA\Header(header="X-Cache-Status", ref="#/components/responses/CacheHeaders/headers/X-Cache-Status"),
     *         @OA\Header(header="X-Cache-Key", ref="#/components/responses/CacheHeaders/headers/X-Cache-Key"),
     *         @OA\Header(header="X-Response-Time", ref="#/components/responses/CacheHeaders/headers/X-Response-Time"),
     *         @OA\Header(header="X-Locale", ref="#/components/responses/CacheHeaders/headers/X-Locale")
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $cacheKey = "static_pages:footer:{$locale}";

        // Cache the resolved resource array, not Eloquent models.
        $pages = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () {
            $pages = StaticPage::with('translations')
                ->active()
                ->inFooter()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return StaticPageFooterResource::collection($pages)->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $pages,
            'message' => 'Static pages retrieved successfully',
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/static-pages/{slug}",
     *     operationId="getStaticPageBySlug",
     *     summary="Get full static page content by slug",
     *     description="Returns the full page (title, slug, content, meta fields) for the requested locale, with English fallback. Looks up by localized slug, then English slug.",
     *     tags={"Static Pages"},
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Localized or English slug of the page",
     *         @OA\Schema(type="string", example="privacy-policy")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Static page retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/StaticPage"),
     *             @OA\Property(property="message", type="string", example="Static page retrieved successfully")
     *         ),
     *         @OA\Header(header="X-Cache-Status", ref="#/components/responses/CacheHeaders/headers/X-Cache-Status"),
     *         @OA\Header(header="X-Cache-Key", ref="#/components/responses/CacheHeaders/headers/X-Cache-Key"),
     *         @OA\Header(header="X-Response-Time", ref="#/components/responses/CacheHeaders/headers/X-Response-Time"),
     *         @OA\Header(header="X-Locale", ref="#/components/responses/CacheHeaders/headers/X-Locale")
     *     ),
     *     @OA\Response(response=404, description="Page not found")
     * )
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $locale = app()->getLocale();
        $cacheKey = "static_pages:show:{$locale}:{$slug}";

        // Cache the resolved resource array, not Eloquent models.
        $page = $this->cacheRemember($cacheKey, CacheService::REFERENCE_DATA_TTL, function () use ($slug, $locale) {
            $page = StaticPage::with('translations')
                ->active()
                ->whereHas('translations', function ($q) use ($slug, $locale) {
                    $q->where('slug', $slug)->whereIn('locale', [$locale, 'en']);
                })
                ->first();

            return $page ? (new StaticPageResource($page))->resolve() : null;
        });

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Static page not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $page,
            'message' => 'Static page retrieved successfully',
        ], 200);
    }
}
