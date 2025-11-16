<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Badge",
 *     type="object",
 *     title="Badge",
 *     description="Badge model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Badge ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=100,
 *         description="Badge code",
 *         example="krk-lamb"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Badge name (localized based on Accept-Language header)",
 *         example="Krk Lamb"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Localized URL-friendly slug",
 *         example="krk-lamb"
 *     ),
 *     @OA\Property(
 *         property="icon",
 *         type="string",
 *         nullable=true,
 *         description="Icon identifier (Font Awesome class)",
 *         example="fa-solid fa-sheep"
 *     )
 * )
 */
class BadgeSchema
{
    // This class is used only for Swagger documentation
}

