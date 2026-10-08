<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LegacyUrlResolver;
use App\Traits\CacheAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyRedirectController extends Controller
{
    use CacheAware;

    private const CACHE_TTL = 86400;

    public function __construct(private LegacyUrlResolver $resolver)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/legacy-redirect",
     *     operationId="resolveLegacyUrl",
     *     tags={"Slug Resolver"},
     *     summary="Resolve an old beachrex.com URL to the new page",
     *     description="Maps a path of the old site (e.g. /hr/hrvatska/dalmacija/tisno-plaze/plaza-tisno) to the new page. The most specific match wins: beach, city, island, region, country. `segments` are the localized slugs after the beaches word: the new URL is /{locale}/{beaches}/{segments...}. Type `map` means the beach map. `language` is the old language prefix of the path (sl, en, hr, de, fr, es, it, ua, ru), if any.",
     *     @OA\Parameter(
     *         name="path",
     *         in="query",
     *         description="Old URL path, with or without the query string",
     *         required=true,
     *         @OA\Schema(type="string", maxLength=500, example="/hr/hrvatska/dalmacija/tisno-plaze/plaza-tisno")
     *     ),
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguage"),
     *     @OA\Response(
     *         response=200,
     *         description="Old URL resolved",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="type", type="string", enum={"beach", "city", "island", "region", "country", "map"}, example="beach"),
     *                 @OA\Property(property="language", type="string", nullable=true, example="hr"),
     *                 @OA\Property(property="segments", type="array", @OA\Items(type="string"), example={"europa", "hrvatska", "dalmacija", "tisno", "plaza-tisno"})
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="No page matches the old URL"),
     *     @OA\Response(response=422, description="Missing or too long path")
     * )
     */
    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate(['path' => 'required|string|max:500']);
        $locale = app()->getLocale();
        $path = strtolower((string) parse_url('/' . ltrim($validated['path'], '/'), PHP_URL_PATH));

        // Cache the resolved array (never models). A day, so renamed slugs and new data show up soon.
        $result = $this->cacheRemember(
            'legacy_redirect:' . $locale . ':' . md5($path),
            self::CACHE_TTL,
            fn () => $this->resolver->resolve($path, $locale) ?? false,
        );

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'No page matches this URL'], 404);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }
}
