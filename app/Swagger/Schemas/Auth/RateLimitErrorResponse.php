<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     schema="RateLimitErrorResponse",
 *     type="object",
 *     required={"error", "message"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         description="Error code",
 *         example="rate_limit_exceeded"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Human-readable error message",
 *         example="Too many authentication attempts. Please try again later."
 *     ),
 *     @OA\Property(
 *         property="retry_after",
 *         type="integer",
 *         description="Seconds until rate limit resets",
 *         example=60
 *     )
 * )
 */
class RateLimitErrorResponse {}

