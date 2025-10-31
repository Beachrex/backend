<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="RecommendedTransportType",
 *     title="Recommended Transport Type",
 *     description="Recommended transport type model (walk, bike, vehicle, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Transport type ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Transport type code",
 *         example="walk"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Transport type name (localized based on Accept-Language header)",
 *         example="Walk"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="walk"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this transport type",
 *         example=280
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this transport type",
 *         @OA\Items(ref="#/components/schemas/RecommendedTransportTypeTranslation")
 *     )
 * )
 */
class RecommendedTransportTypeSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="RecommendedTransportTypeTranslation",
 *     title="Recommended Transport Type Translation",
 *     description="Translation for a recommended transport type",
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
 *         description="Translated transport type name",
 *         example="Walk"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="walk"
 *     )
 * )
 */
class RecommendedTransportTypeTranslationSchema
{
    // This class is used only for Swagger documentation
}

