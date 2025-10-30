<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachType",
 *     title="Beach Type",
 *     description="Beach surface type model (sand, stones, rock, grass, concrete, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Beach type ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Beach type code",
 *         example="sand"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Beach type name (localized based on Accept-Language header)",
 *         example="Sand"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="sand-beaches"
 *     ),
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         description="Current locale used for name and slug (based on Accept-Language header)",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="show_filter",
 *         type="boolean",
 *         description="Whether to show this beach type in filters",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this surface type",
 *         example=150
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this beach type",
 *         @OA\Items(ref="#/components/schemas/BeachTypeTranslation")
 *     )
 * )
 */
class BeachTypeSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="BeachTypeTranslation",
 *     title="Beach Type Translation",
 *     description="Translation for a beach type",
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
 *         description="Translated beach type name",
 *         example="Sand"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="sand-beaches"
 *     )
 * )
 */
class BeachTypeTranslationSchema
{
    // This class is used only for Swagger documentation
}

