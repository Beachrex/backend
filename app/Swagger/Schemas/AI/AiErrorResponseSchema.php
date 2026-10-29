<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiErrorResponse",
 *     title="AI Error Response",
 *     description="Error response from AI endpoints",
 *     type="object",
 *     required={"error", "message"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         description="Machine-readable error code",
 *         enum={
 *             "payment_required",
 *             "rate_limit_exceeded",
 *             "token_budget_exceeded",
 *             "service_unavailable",
 *             "invalid_request",
 *             "unauthorized",
 *             "forbidden",
 *             "session_expired",
 *             "internal_error"
 *         },
 *         example="rate_limit_exceeded"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Human-readable error message",
 *         example="You have exceeded the rate limit for free tier"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AiPaymentRequiredError",
 *     title="AI Payment Required Error (402)",
 *     description="Error response when paid feature is requested",
 *     type="object",
 *     required={"error", "message", "product", "purchase_url"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         enum={"payment_required"},
 *         example="payment_required"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Multi-day itineraries require payment"
 *     ),
 *     @OA\Property(
 *         property="product",
 *         type="object",
 *         description="Product information",
 *         @OA\Property(property="type", type="string", example="itinerary"),
 *         @OA\Property(property="duration_days", type="integer", example=7),
 *         @OA\Property(property="price", type="number", format="float", example=39),
 *         @OA\Property(property="currency", type="string", example="USD")
 *     ),
 *     @OA\Property(
 *         property="purchase_url",
 *         type="string",
 *         format="uri",
 *         example="https://beachrex.com/purchase/itinerary/xyz789"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AiRateLimitError",
 *     title="AI Rate Limit Error (429)",
 *     description="Error response when rate limit is exceeded",
 *     type="object",
 *     required={"error", "message", "limit", "reset_at", "upgrade_url"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         enum={"rate_limit_exceeded"},
 *         example="rate_limit_exceeded"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="You have exceeded the rate limit for free tier"
 *     ),
 *     @OA\Property(
 *         property="limit",
 *         type="integer",
 *         description="Rate limit threshold",
 *         example=100
 *     ),
 *     @OA\Property(
 *         property="reset_at",
 *         type="string",
 *         format="date-time",
 *         description="When the rate limit resets (ISO 8601)",
 *         example="2025-12-22T15:00:00Z"
 *     ),
 *     @OA\Property(
 *         property="upgrade_url",
 *         type="string",
 *         format="uri",
 *         example="https://beachrex.com/pricing"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AiTokenBudgetError",
 *     title="AI Token Budget Error (413)",
 *     description="Error response when token budget is exceeded",
 *     type="object",
 *     required={"error", "message", "estimated_tokens", "max_tokens", "suggestion"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         enum={"token_budget_exceeded"},
 *         example="token_budget_exceeded"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="Your query is too complex for the free tier"
 *     ),
 *     @OA\Property(
 *         property="estimated_tokens",
 *         type="integer",
 *         description="Estimated tokens required",
 *         example=5200
 *     ),
 *     @OA\Property(
 *         property="max_tokens",
 *         type="integer",
 *         description="Maximum tokens allowed",
 *         example=4000
 *     ),
 *     @OA\Property(
 *         property="suggestion",
 *         type="string",
 *         example="Simplify your query or upgrade to paid tier"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AiServiceUnavailableError",
 *     title="AI Service Unavailable Error (503)",
 *     description="The AI is not available right now. error=service_unavailable: switched off on the server (retry_after null) or a daily cost limit is reached (reason=daily_limit, retry_after = next local midnight, Europe/Ljubljana by default; the chat is back on by itself then). error=ai_unavailable: the AI provider is busy, out of quota or timed out (Retry-After: 60).",
 *     type="object",
 *     required={"error", "message"},
 *     @OA\Property(property="error", type="string", enum={"service_unavailable", "ai_unavailable"}, example="service_unavailable"),
 *     @OA\Property(property="reason", type="string", nullable=true, enum={"daily_limit"}, description="Present when a daily cost limit is reached", example="daily_limit"),
 *     @OA\Property(property="message", type="string", example="The AI assistant has reached its daily limit and is resting until tomorrow."),
 *     @OA\Property(property="retry_after", type="string", format="date-time", nullable=true, description="When to try again (ISO 8601 with offset)", example="2026-10-30T00:00:00+01:00"),
 *     @OA\Property(property="request_id", type="string", format="uuid", nullable=true, description="Only with error=ai_unavailable")
 * )
 *
 * @OA\Schema(
 *     schema="AiTurnstileError",
 *     title="AI chat robot check (403)",
 *     description="Cloudflare Turnstile is on and the request has no valid token: run the Turnstile widget (action ai_chat) and send the message again with turnstile_token.",
 *     type="object",
 *     required={"error", "message"},
 *     @OA\Property(property="error", type="string", enum={"turnstile_required", "turnstile_failed"}, example="turnstile_required"),
 *     @OA\Property(property="message", type="string", example="Please confirm that you are not a robot.")
 * )
 */
class AiErrorResponseSchema {}

