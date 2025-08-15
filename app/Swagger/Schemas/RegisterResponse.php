<?php
namespace App\Swagger\Schemas;
/**
 * @OA\Schema(
 *     schema="RegisterResponse",
 *     @OA\Property(property="user", type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", example="john@example.com"),
 *     ),
 *     @OA\Property(property="token", type="string", example="abc123token")
 * )
 */
class RegisterResponse {}
