<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     schema="GoogleCallbackResponse",
 *     type="object",
 *     required={"token", "token_type", "expires_in", "user"},
 *     @OA\Property(
 *         property="token",
 *         type="string",
 *         description="Sanctum personal access token (Bearer token)",
 *         example="1|8C05td6vuOSBLDMvWjg2EpxumuSO9h4h5tP4GGEe63440ff9"
 *     ),
 *     @OA\Property(
 *         property="token_type",
 *         type="string",
 *         description="Token type (always 'Bearer')",
 *         example="Bearer"
 *     ),
 *     @OA\Property(
 *         property="expires_in",
 *         type="integer",
 *         description="Token expiration time in seconds. 0 means token does not expire (Sanctum PAT default behavior)",
 *         example=0
 *     ),
 *     @OA\Property(
 *         property="user",
 *         ref="#/components/schemas/MeResponse",
 *         description="Authenticated user profile"
 *     )
 * )
 */
class GoogleCallbackResponse {}

