<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="TokenResponse",
 *     title="Token Response",
 *     type="object",
 *     @OA\Property(property="token", type="string", example="abc123"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 */
class TokenResponse {}
