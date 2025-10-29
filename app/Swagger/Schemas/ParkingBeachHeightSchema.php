<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="ParkingBeachHeight",
 *     title="Parking Beach Height",
 *     description="Parking beach height model (0m, 5m, 10m, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Parking beach height ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Parking beach height code",
 *         example="0m"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Parking beach height name (localized based on Accept-Language header)",
 *         example="0m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="0m"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this parking beach height",
 *         example=12
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this parking beach height",
 *         @OA\Items(ref="#/components/schemas/ParkingBeachHeightTranslation")
 *     )
 * )
 */
class ParkingBeachHeightSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="ParkingBeachHeightTranslation",
 *     title="Parking Beach Height Translation",
 *     description="Translation for a parking beach height",
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
 *         description="Translated parking beach height name",
 *         example="0m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="0m"
 *     )
 * )
 */
class ParkingBeachHeightTranslationSchema
{
    // This class is used only for Swagger documentation
}

