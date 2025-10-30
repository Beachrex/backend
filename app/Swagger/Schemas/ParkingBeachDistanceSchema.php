<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="ParkingBeachDistance",
 *     title="Parking Beach Distance",
 *     description="Parking beach distance model (10m, 30m, 50m, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Parking beach distance ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Parking beach distance code",
 *         example="10m"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Parking beach distance name (localized based on Accept-Language header)",
 *         example="10m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="10m"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this parking beach distance",
 *         example=15
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this parking beach distance",
 *         @OA\Items(ref="#/components/schemas/ParkingBeachDistanceTranslation")
 *     )
 * )
 */
class ParkingBeachDistanceSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="ParkingBeachDistanceTranslation",
 *     title="Parking Beach Distance Translation",
 *     description="Translation for a parking beach distance",
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
 *         description="Translated parking beach distance name",
 *         example="10m"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="10m"
 *     )
 * )
 */
class ParkingBeachDistanceTranslationSchema
{
    // This class is used only for Swagger documentation
}

