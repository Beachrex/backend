<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="City",
 *     type="object",
 *     title="City",
 *     description="City model. Can be filtered by country_code, region_code, or island_code in list endpoints.",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="City ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=100,
 *         description="City code",
 *         example="split"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="City name",
 *         example="Split"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Localized URL-friendly slug",
 *         example="split"
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
 *         example=4
 *     ),
 *     @OA\Property(
 *         property="island_id",
 *         type="integer",
 *         nullable=true,
 *         description="Island ID (use island_code parameter to filter)",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="latitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="City latitude (centroid of beaches)",
 *         example=43.5081
 *     ),
 *     @OA\Property(
 *         property="longitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="City longitude (centroid of beaches)",
 *         example=16.4402
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
 *         description="Number of beaches in this city",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="accommodation_count",
 *         type="integer",
 *         description="Number of accommodations in this city (hardcoded to 0 until booking integration)",
 *         example=0
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
 *         description="Badges associated with this city (when included)",
 *         @OA\Items(ref="#/components/schemas/Badge")
 *     ),
 *     @OA\Property(
 *         property="articles",
 *         type="array",
 *         description="Articles associated with this city (when included)",
 *         @OA\Items(ref="#/components/schemas/Article")
 *     )
 * )
 */
class CitySchema
{
    // This class is used only for Swagger documentation
}

