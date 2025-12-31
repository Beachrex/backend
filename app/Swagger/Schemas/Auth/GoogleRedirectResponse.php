<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     schema="GoogleRedirectResponse",
 *     type="object",
 *     required={"url"},
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         format="uri",
 *         description="Google OAuth authorization URL to redirect the user to",
 *         example="https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...&response_type=code&scope=openid%20profile%20email&state=..."
 *     )
 * )
 */
class GoogleRedirectResponse {}

