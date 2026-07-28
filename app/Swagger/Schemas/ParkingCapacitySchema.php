<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="ParkingCapacity",
 *     title="Parking Capacity",
 *     description="Parking capacity model (5, 10, 15, 20, etc.)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Parking capacity ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Parking capacity code",
 *         example="5"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Parking capacity name (localized based on Accept-Language header)",
 *         example="5"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="5"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this parking capacity",
 *         example=23
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this parking capacity",
 *         @OA\Items(ref="#/components/schemas/ParkingCapacityTranslation")
 *     )
 * )
 */
class ParkingCapacitySchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="ParkingCapacityTranslation",
 *     title="Parking Capacity Translation",
 *     description="Translation for a parking capacity",
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
 *         description="Translated parking capacity name",
 *         example="5"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="5"
 *     )
 * )
 */
class ParkingCapacityTranslationSchema
{
    // This class is used only for Swagger documentation
}

