<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="LocationInfrastructure",
 *     title="Location Infrastructure",
 *     description="Location infrastructure model (WC, shower, parking, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Infrastructure ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Infrastructure code",
 *         example="wc"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Infrastructure name (localized based on Accept-Language header)",
 *         example="WC"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="wc"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this infrastructure",
 *         example=450
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this infrastructure",
 *         @OA\Items(ref="#/components/schemas/LocationInfrastructureTranslation")
 *     )
 * )
 */
class LocationInfrastructureSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="LocationInfrastructureTranslation",
 *     title="Location Infrastructure Translation",
 *     description="Translation for a location infrastructure type",
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
 *         description="Translated infrastructure name",
 *         example="WC"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="wc"
 *     )
 * )
 */
class LocationInfrastructureTranslationSchema
{
    // This class is used only for Swagger documentation
}

