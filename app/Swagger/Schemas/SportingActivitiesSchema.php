<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="SportingActivities",
 *     title="Sporting Activities",
 *     description="Sporting activities model (swimming, diving, surfing, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Activity ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Activity code",
 *         example="swimming"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Activity name (localized based on Accept-Language header)",
 *         example="Swimming"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="swimming"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this activity",
 *         example=520
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this activity",
 *         @OA\Items(ref="#/components/schemas/SportingActivitiesTranslation")
 *     )
 * )
 */
class SportingActivitiesSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="SportingActivitiesTranslation",
 *     title="Sporting Activities Translation",
 *     description="Translation for a sporting activity",
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
 *         description="Translated activity name",
 *         example="Swimming"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="swimming"
 *     )
 * )
 */
class SportingActivitiesTranslationSchema
{
    // This class is used only for Swagger documentation
}

