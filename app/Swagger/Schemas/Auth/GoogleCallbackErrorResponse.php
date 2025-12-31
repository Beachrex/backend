<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     schema="GoogleCallbackErrorResponse",
 *     type="object",
 *     required={"error", "message"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         description="Error code",
 *         enum={"invalid_state", "invalid_code", "google_api_error", "user_creation_failed"},
 *         example="invalid_state"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Human-readable error message",
 *         example="Invalid or expired state parameter. Please try again."
 *     ),
 *     @OA\Property(
 *         property="details",
 *         type="object",
 *         nullable=true,
 *         description="Additional error details (only in development mode)",
 *         example={"google_error": "invalid_grant"}
 *     )
 * )
 */
class GoogleCallbackErrorResponse {}

