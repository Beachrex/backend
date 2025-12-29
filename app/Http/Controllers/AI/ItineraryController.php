<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\ItineraryRequest;
use App\Services\AI\PlanEnforcementService;
use App\Services\AI\ItineraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * AI Itinerary Controller
 *
 * Handles trip itinerary generation (PRIMARY REVENUE FEATURE).
 * 
 * Source: docs/ai/implementation/PHASE_1_PROPOSAL.md
 *
 * Enforcement order (from middleware + controller):
 * 1. OptionalAuth (middleware)
 * 2. CircuitBreaker (middleware)
 * 3. RateLimit (middleware)
 * 4. TokenBudget (middleware)
 * 5. PlanEnforcement (controller - 402 for multi-day itineraries)
 * 6. LLM call (only after all checks pass)
 */
class ItineraryController extends Controller
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
        private readonly ItineraryService $itineraryService,
    ) {}

    /**
     * Generate trip itinerary.
     *
     * POST /api/v1/ai/itineraries
     *
     * @OA\Post(
     *     path="/api/v1/ai/itineraries",
     *     summary="Generate multi-day beach itinerary",
     *     description="Generate an optimized multi-day beach itinerary (3, 7, or 14 days). This is a PAID feature - multi-day itineraries (2+ days) require payment. Returns 402 Payment Required if user is not entitled.",
     *     operationId="generateItinerary",
     *     tags={"AI Agent"},
     *     security={{"sanctum": {}}, {}},
     *     @OA\Parameter(
     *         ref="#/components/parameters/AcceptLanguage"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Itinerary request with destination, duration, and preferences",
     *         @OA\JsonContent(ref="#/components/schemas/AiItineraryRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful itinerary generation",
     *         @OA\JsonContent(ref="#/components/schemas/AiItineraryResponse"),
     *         @OA\Header(
     *             header="X-Request-ID",
     *             description="Unique request identifier for tracking",
     *             @OA\Schema(type="string", format="uuid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid input (missing destination, invalid duration, etc.)",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiErrorResponse",
     *             example={
     *                 "error": "invalid_request",
     *                 "message": "Duration must be 3, 7, or 14 days"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=402,
     *         description="Payment Required - Multi-day itineraries require payment",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiPaymentRequiredError",
     *             example={
     *                 "error": "payment_required",
     *                 "message": "Multi-day itineraries require payment",
     *                 "product": {
     *                     "type": "itinerary",
     *                     "duration_days": 7,
     *                     "price": 39,
     *                     "currency": "USD"
     *                 },
     *                 "purchase_url": "https://beachrex.com/pricing"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=413,
     *         description="Payload Too Large - Token budget exceeded",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiTokenBudgetError",
     *             example={
     *                 "error": "token_budget_exceeded",
     *                 "message": "Request exceeds token budget for free tier",
     *                 "limit": 4000,
     *                 "estimated": 5200,
     *                 "upgrade_url": "https://beachrex.com/pricing"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too Many Requests - Rate limit exceeded",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiRateLimitError",
     *             example={
     *                 "error": "rate_limit_exceeded",
     *                 "message": "You have exceeded the rate limit for free tier",
     *                 "limit": 100,
     *                 "reset_at": "2025-12-29T15:00:00Z",
     *                 "upgrade_url": "https://beachrex.com/pricing"
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error - Unexpected failure",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiErrorResponse",
     *             example={
     *                 "error": "internal_error",
     *                 "message": "An unexpected error occurred. Please try again."
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=503,
     *         description="Service Unavailable - Circuit breaker triggered or AI service disabled",
     *         @OA\JsonContent(
     *             ref="#/components/schemas/AiServiceUnavailableError",
     *             example={
     *                 "error": "service_unavailable",
     *                 "message": "AI service is temporarily unavailable due to high demand",
     *                 "retry_after": "2025-12-30T00:00:00Z",
     *                 "fallback_url": "https://beachrex.com/search"
     *             }
     *         )
     *     )
     * )
     *
     * @param ItineraryRequest $request
     * @return JsonResponse
     */
    public function generate(ItineraryRequest $request): JsonResponse
    {
        $startTime = microtime(true);
        $requestId = (string) Str::uuid();

        try {
            // Extract validated data
            $durationDays = $request->input('duration_days');
            $user = $request->user();

            // CRITICAL: Check plan enforcement (402 for multi-day itineraries)
            // Multi-day itineraries (2+ days) are NEVER free - this is PRIMARY REVENUE
            $enforcementCheck = $this->planEnforcement->checkMultiDayItinerary($user, $durationDays);
            if ($enforcementCheck !== null) {
                return $enforcementCheck;
            }

            // Generate itinerary using ItineraryService
            $itinerary = $this->itineraryService->generate($request, $requestId);

            // Add request ID header
            return response()->json($itinerary, 200)
                ->header('X-Request-ID', $requestId);

        } catch (\Exception $e) {
            // Log error (no PII)
            \Log::error('Itinerary generation failed', [
                'request_id' => $requestId,
                'error' => $e->getMessage(),
                'duration_days' => $request->input('duration_days'),
                'country_code' => $request->input('destination.country_code'),
            ]);

            // Return safe error response (no paid content leak)
            return response()->json([
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }
}

