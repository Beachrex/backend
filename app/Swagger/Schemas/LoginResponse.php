<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     @OA\Property(property="token", type="string", example="2|KJDF82kdjkfj..."),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 */
class LoginResponse {}
