<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="SwimmingDepthOfOneM",
 *     title="Swimming Depth of 1m",
 *     description="Swimming depth model - distance to reach 1m depth (0m, 3m, 5m, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Swimming depth ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Swimming depth code",
 *         example="3m"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Swimming depth name (localized based on Accept-Language header)",
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
 *         description="Number of beaches with this swimming depth",
 *         example=8
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this swimming depth",
 *         @OA\Items(ref="#/components/schemas/SwimmingDepthOfOneMTranslation")
 *     )
 * )
 */
class SwimmingDepthOfOneMSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="SwimmingDepthOfOneMTranslation",
 *     title="Swimming Depth Translation",
 *     description="Translation for a swimming depth",
 *     type="object",
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         description="Language code (en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk, da, pt, nl, el, sq)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Translated swimming depth name",
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
class SwimmingDepthOfOneMTranslationSchema
{
    // This class is used only for Swagger documentation
}

