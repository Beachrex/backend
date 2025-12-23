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
 *     description="Error response when AI service is temporarily unavailable",
 *     type="object",
 *     required={"error", "message", "retry_after", "fallback_url"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         enum={"service_unavailable"},
 *         example="service_unavailable"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         example="AI service is temporarily unavailable due to high costs"
 *     ),
 *     @OA\Property(
 *         property="retry_after",
 *         type="integer",
 *         description="Seconds until retry",
 *         example=3600
 *     ),
 *     @OA\Property(
 *         property="fallback_url",
 *         type="string",
 *         format="uri",
 *         description="Fallback URL for regular search",
 *         example="https://beachrex.com/search"
 *     )
 * )
 */
class AiErrorResponseSchema {}

