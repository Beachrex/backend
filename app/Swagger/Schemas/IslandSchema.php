<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Island",
 *     type="object",
 *     title="Island",
 *     description="Island model. Can be filtered by country_code or region_code in list endpoints.",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Island ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Island code",
 *         example="krk"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Island name",
 *         example="Krk"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Localized URL-friendly slug",
 *         example="krk"
 *     ),
 *     @OA\Property(
 *         property="country_code",
 *         type="string",
 *         maxLength=2,
 *         description="Two-letter country code (can be used as filter parameter)",
 *         example="HR"
 *     ),
 *     @OA\Property(
 *         property="region_id",
 *         type="integer",
 *         nullable=true,
 *         description="Region ID (use region_code parameter to filter)",
 *         example=7
 *     ),
 *     @OA\Property(
 *         property="latitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="Island latitude (centroid of beaches)",
 *         example=45.0272
 *     ),
 *     @OA\Property(
 *         property="longitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="Island longitude (centroid of beaches)",
 *         example=14.5719
 *     ),
 *     @OA\Property(
 *         property="main_image",
 *         type="string",
 *         nullable=true,
 *         description="Relative path to main image from most viewed beach",
 *         example="img/beaches/hr-13-k-istra/hr-13-k-istra-sl2.jpg"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches on this island",
 *         example=15
 *     ),
 *     @OA\Property(
 *         property="accommodation_count",
 *         type="integer",
 *         description="Number of accommodations on this island (hardcoded to 0 until booking integration)",
 *         example=0
 *     ),
 *     @OA\Property(
 *         property="location",
 *         type="object",
 *         description="Location slugs for URL building (included when relationships are loaded)",
 *         @OA\Property(property="continent_slug", type="string", example="europe"),
 *         @OA\Property(property="country_slug", type="string", example="croatia"),
 *         @OA\Property(property="region_slug", type="string", nullable=true, example="kvarner")
 *     ),
 *     @OA\Property(
 *         property="country",
 *         ref="#/components/schemas/Country",
 *         description="Country information (when included)"
 *     ),
 *     @OA\Property(
 *         property="region",
 *         ref="#/components/schemas/Region",
 *         description="Region information (when included)"
 *     ),
 *     @OA\Property(
 *         property="badges",
 *         type="array",
 *         description="Badges associated with this island (when included)",
 *         @OA\Items(ref="#/components/schemas/Badge")
 *     ),
 *     @OA\Property(
 *         property="articles",
 *         type="array",
 *         description="Articles associated with this island (when included)",
 *         @OA\Items(ref="#/components/schemas/Article")
 *     )
 * )
 */
class IslandSchema
{
    // This class is used only for Swagger documentation
}

