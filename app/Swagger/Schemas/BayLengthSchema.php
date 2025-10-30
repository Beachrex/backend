<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BayLength",
 *     title="Bay Length",
 *     description="Bay length model (3m, 5m, 10m, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Bay length ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Bay length code",
 *         example="3m"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Bay length name (localized based on Accept-Language header)",
 *         example="3m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="3m"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this bay length",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this bay length",
 *         @OA\Items(ref="#/components/schemas/BayLengthTranslation")
 *     )
 * )
 */
class BayLengthSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="BayLengthTranslation",
 *     title="Bay Length Translation",
 *     description="Translation for a bay length",
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
 *         description="Translated bay length name",
 *         example="3m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="3m"
 *     )
 * )
 */
class BayLengthTranslationSchema
{
    // This class is used only for Swagger documentation
}

