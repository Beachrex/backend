<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     schema="LogoutResponse",
 *     type="object",
 *     required={"success"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean",
 *         description="Indicates successful token revocation",
 *         example=true
 *     )
 * )
 */
class LogoutResponse {}

