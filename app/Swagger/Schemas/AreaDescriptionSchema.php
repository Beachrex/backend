<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="AreaDescription",
 *     title="Area Description",
 *     description="Area description model (urban, partly urban, natural)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Area description ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Area description code",
 *         example="l_urban"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Area description name (localized based on Accept-Language header)",
 *         example="Urban"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="urban"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this area description",
 *         example=75
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this area description",
 *         @OA\Items(ref="#/components/schemas/AreaDescriptionTranslation")
 *     )
 * )
 */
class AreaDescriptionSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="AreaDescriptionTranslation",
 *     title="Area Description Translation",
 *     description="Translation for an area description",
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
 *         description="Translated area description name",
 *         example="Urban"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="urban"
 *     )
 * )
 */
class AreaDescriptionTranslationSchema
{
    // This class is used only for Swagger documentation
}

