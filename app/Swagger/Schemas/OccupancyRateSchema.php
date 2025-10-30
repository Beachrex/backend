<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="OccupancyRate",
 *     title="Occupancy Rate",
 *     description="Occupancy rate model (small, middle, big)",
 *     type="object",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Occupancy rate ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Occupancy rate code",
 *         example="small"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Occupancy rate name (localized based on Accept-Language header)",
 *         example="Small"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug (localized based on Accept-Language header)",
 *         example="small"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches with this occupancy rate",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="translations",
 *         type="array",
 *         description="All available translations for this occupancy rate",
 *         @OA\Items(ref="#/components/schemas/OccupancyRateTranslation")
 *     )
 * )
 */
class OccupancyRateSchema
{
    // This class is used only for Swagger documentation
}

/**
 * @OA\Schema(
 *     schema="OccupancyRateTranslation",
 *     title="Occupancy Rate Translation",
 *     description="Translation for an occupancy rate",
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
 *         description="Translated occupancy rate name",
 *         example="Small"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="URL-friendly slug",
 *         example="small"
 *     )
 * )
 */
class OccupancyRateTranslationSchema
{
    // This class is used only for Swagger documentation
}

