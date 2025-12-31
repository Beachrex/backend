<?php

namespace App\Swagger\Schemas\Auth;

/**
 * @OA\Schema(
 *     schema="MeResponse",
 *     type="object",
 *     required={"id", "name", "email", "created_at"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="User ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="User's full name",
 *         example="John Doe"
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         description="User's email address",
 *         example="john.doe@gmail.com"
 *     ),
 *     @OA\Property(
 *         property="avatar_url",
 *         type="string",
 *         format="uri",
 *         nullable=true,
 *         description="User's avatar/profile picture URL (from Google)",
 *         example="https://lh3.googleusercontent.com/a/..."
 *     ),
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         nullable=true,
 *         description="User's preferred locale (ISO 639-1 language code)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Account creation timestamp",
 *         example="2025-12-31T10:30:00.000000Z"
 *     )
 * )
 */
class MeResponse {}

