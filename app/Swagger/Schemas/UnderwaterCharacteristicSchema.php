<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="UnderwaterCharacteristic",
 *     title="Underwater Characteristic",
 *     description="Underwater characteristic model (sand ground, stones, rocks, grass, coral)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Characteristic ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Characteristic code",
 *         example="sand_ground"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Characteristic name (localized based on Accept-Language header)",
 *         example="Sand Ground"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="sand-ground"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this characteristic",
 *         example=450
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this characteristic",
 *         @OA\Items(ref="#/components/schemas/UnderwaterCharacteristicTranslation")
 *     )
 * )
 */
class UnderwaterCharacteristicSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="UnderwaterCharacteristicTranslation",
 *     title="Underwater Characteristic Translation",
 *     description="Translation for an underwater characteristic",
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
 *         description="Translated characteristic name",
 *         example="Sand Ground"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="sand-ground"
 *     )
 * )
 */
class UnderwaterCharacteristicTranslationSchema
{
    // This class is used only for Swagger documentation
}

