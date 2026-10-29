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
     *     description="Switches the owner sets in admin (System → Site settings), per environment. Clients hide a feature that is off. ai_assistant_enabled = false: the AI beach guide (chat, itineraries, buying credits) is off and its /api/v1/ai/* routes answer 503 ai_disabled.",
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
     *                 @OA\Property(property="ai_assistant_enabled", type="boolean", example=true)
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
            ],
        ]);
    }
}
