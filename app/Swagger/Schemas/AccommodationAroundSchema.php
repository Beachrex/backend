<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="AccommodationAround",
 *     title="Accommodation Around",
 *     description="Accommodation type model (hotel, apartments, camp, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Accommodation type ID",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Accommodation type code",
 *         example="hotel"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Accommodation type name (localized based on Accept-Language header)",
 *         example="Hotel"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="hotel"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this accommodation type",
 *         example=150
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this accommodation type",
 *         @OA\Items(ref="#/components/schemas/AccommodationAroundTranslation")
 *     )
 * )
 */
class AccommodationAroundSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="AccommodationAroundTranslation",
 *     title="Accommodation Around Translation",
 *     description="Translation for an accommodation type",
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
 *         description="Translated accommodation type name",
 *         example="Hotel"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="hotel"
 *     )
 * )
 */
class AccommodationAroundTranslationSchema
{
    // This class is used only for Swagger documentation
}

