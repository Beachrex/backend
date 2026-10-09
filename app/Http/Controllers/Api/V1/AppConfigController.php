<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * B-M1 (TASK-005 mobile app): versions, store links and feature flags the app reads on start.
 */
class AppConfigController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/app-config",
     *     operationId="getAppConfig",
     *     tags={"Mobile"},
     *     summary="Mobile app configuration",
     *     description="Per platform: the lowest app version still supported (below it the app asks the user to update), the latest version and the store link; plus feature flags. Public, cacheable for 5 minutes.",
     *     @OA\Response(
     *         response=200,
     *         description="App configuration",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="ios", ref="#/components/schemas/AppPlatformConfig"),
     *                 @OA\Property(property="android", ref="#/components/schemas/AppPlatformConfig"),
     *                 @OA\Property(property="features", type="object",
     *                     @OA\Property(property="ai_chat", type="boolean"),
     *                     @OA\Property(property="itinerary", type="boolean"),
     *                     @OA\Property(property="favorites", type="boolean"),
     *                     @OA\Property(property="native_sign_in", type="boolean")
     *                 ),
     *                 @OA\Property(property="api_version", type="string", example="v1")
     *             )
     *         )
     *     )
     * )
     *
     * @OA\Schema(
     *     schema="AppPlatformConfig",
     *     type="object",
     *     @OA\Property(property="min_supported_version", type="string", example="1.0.0"),
     *     @OA\Property(property="latest_version", type="string", example="1.2.0"),
     *     @OA\Property(property="store_url", type="string", nullable=true, example="https://apps.apple.com/app/id000000000")
     * )
     */
    public function show(): JsonResponse
    {
        $platform = fn (string $p) => [
            'min_supported_version' => (string) config("mobile.{$p}.min_supported_version"),
            'latest_version' => (string) config("mobile.{$p}.latest_version"),
            'store_url' => config("mobile.{$p}.store_url") ?: null,
        ];

        return response()->json(['data' => [
            'ios' => $platform('ios'),
            'android' => $platform('android'),
            'features' => array_map('boolval', (array) config('mobile.features')),
            'api_version' => 'v1',
        ]])->header('Cache-Control', 'public, max-age=300');
    }
}
