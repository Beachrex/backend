<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="NaturalLocationCondition",
 *     title="Natural Location Condition",
 *     description="Natural location condition model (sand types, stones, rocks, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Condition ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Condition code",
 *         example="white_sand"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Condition name (localized based on Accept-Language header)",
 *         example="White Sand"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="white-sand"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this condition",
 *         example=320
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this condition",
 *         @OA\Items(ref="#/components/schemas/NaturalLocationConditionTranslation")
 *     )
 * )
 */
class NaturalLocationConditionSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="NaturalLocationConditionTranslation",
 *     title="Natural Location Condition Translation",
 *     description="Translation for a natural location condition",
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
 *         description="Translated condition name",
 *         example="White Sand"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="white-sand"
 *     )
 * )
 */
class NaturalLocationConditionTranslationSchema
{
    // This class is used only for Swagger documentation
}

