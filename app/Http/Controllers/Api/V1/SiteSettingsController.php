<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

/** TASK-009: public, read-only site switches set in admin (System → Site settings). */
class SiteSettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/site-settings",
     *     operationId="getSiteSettings",
     *     summary="Public site switches",
     *     description="Switches the owner sets in admin (System → Site settings), per environment. Clients hide a feature that is off. ai_assistant_enabled = false: the AI beach chat is off and POST /api/v1/ai/chat answers 503 ai_disabled. ai_trip_planner_enabled = false: the paid AI trip planner is off and POST /api/v1/ai/itineraries, POST /api/v1/ai/itinerary-plans, GET /api/v1/payments/products and POST /api/v1/payments/stripe/checkout answer 503 ai_trip_planner_disabled. The two switches are independent. ads_enabled = false (default): the website loads no Google ads (AdSense) script and shows no ad slots.",
     *     tags={"Site settings"},
     *     @OA\Response(
     *         response=200,
     *         description="Current switches",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="ai_assistant_enabled", type="boolean", example=true, description="AI beach chat on"),
     *                 @OA\Property(property="ai_trip_planner_enabled", type="boolean", example=true, description="Paid AI trip planner on"),
     *                 @OA\Property(property="ads_enabled", type="boolean", example=false, description="Google ads (AdSense) on the website; off by default")
     *             )
     *         )
     *     )
     * )
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'ai_assistant_enabled' => SiteSetting::aiAssistantEnabled(),
                'ai_trip_planner_enabled' => SiteSetting::aiTripPlannerEnabled(),
                'ads_enabled' => SiteSetting::adsEnabled(),
            ],
        ]);
    }
}
