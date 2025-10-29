<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Waterbody",
 *     title="Waterbody",
 *     description="Waterbody type model (sea, lake, river, pool)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Waterbody ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Waterbody code",
 *         example="sea"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Waterbody name (localized based on Accept-Language header)",
 *         example="Sea"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="sea"
 *     ),
 *     @OA\Property(
 *         property="show_filter",
 *         type="boolean",
 *         description="Whether to show this waterbody in filters",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this waterbody type",
 *         example=42
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this waterbody",
 *         @OA\Items(ref="#/components/schemas/WaterbodyTranslation")
 *     )
 * )
 */
class WaterbodySchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="WaterbodyTranslation",
 *     title="Waterbody Translation",
 *     description="Translation for a waterbody type",
 *     type="object",
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         description="Language code (en, sl, de, hr, fr, es, it, ua, ru)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Translated waterbody name",
 *         example="Sea"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="sea"
 *     )
 * )
 */
class WaterbodyTranslationSchema
{
    // This class is used only for Swagger documentation
}

