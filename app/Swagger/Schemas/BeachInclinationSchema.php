<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachInclination",
 *     title="Beach Inclination",
 *     description="Beach inclination model (small_slope, normal, increase, sudden_drop, different_mixed)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Beach inclination ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Beach inclination code",
 *         example="small_slope"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Beach inclination name (localized based on Accept-Language header)",
 *         example="Small slope"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="small-slope"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this inclination",
 *         example=12
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this beach inclination",
 *         @OA\Items(ref="#/components/schemas/BeachInclinationTranslation")
 *     )
 * )
 */
class BeachInclinationSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="BeachInclinationTranslation",
 *     title="Beach Inclination Translation",
 *     description="Translation for a beach inclination",
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
 *         description="Translated beach inclination name",
 *         example="Small slope"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="small-slope"
 *     )
 * )
 */
class BeachInclinationTranslationSchema
{
    // This class is used only for Swagger documentation
}

