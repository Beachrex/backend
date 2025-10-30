<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="LocationLength",
 *     title="Location Length",
 *     description="Location length model (5m, 10m, 20m, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Location length ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Location length code",
 *         example="5m"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Location length name (localized based on Accept-Language header)",
 *         example="5m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="5m"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this location length",
 *         example=8
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this location length",
 *         @OA\Items(ref="#/components/schemas/LocationLengthTranslation")
 *     )
 * )
 */
class LocationLengthSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="LocationLengthTranslation",
 *     title="Location Length Translation",
 *     description="Translation for a location length",
 *     type="object",
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         description="Language code (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Translated location length name",
 *         example="5m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="5m"
 *     )
 * )
 */
class LocationLengthTranslationSchema
{
    // This class is used only for Swagger documentation
}

