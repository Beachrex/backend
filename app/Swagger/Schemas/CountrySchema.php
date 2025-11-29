<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Country",
 *     type="object",
 *     title="Country",
 *     description="Country model",
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=2,
 *         description="Two-letter country code (ISO 3166-1 alpha-2)",
 *         example="SI"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=100,
 *         description="Country name",
 *         example="Slovenia"
 *     ),
 *     @OA\Property(
 *         property="full_name",
 *         type="string",
 *         maxLength=255,
 *         description="Full official country name",
 *         example="Republic of Slovenia"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Localized URL-friendly slug",
 *         example="slovenia"
 *     ),
 *     @OA\Property(
 *         property="iso3",
 *         type="string",
 *         maxLength=3,
 *         description="Three-letter country code (ISO 3166-1 alpha-3)",
 *         example="SVN"
 *     ),
 *     @OA\Property(
 *         property="continent_code",
 *         type="string",
 *         maxLength=2,
 *         description="Two-letter continent code",
 *         example="EU"
 *     ),
 *     @OA\Property(
 *         property="latitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="Country latitude (centroid of beaches)",
 *         example=46.1512
 *     ),
 *     @OA\Property(
 *         property="longitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="Country longitude (centroid of beaches)",
 *         example=14.9955
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
 *         description="Number of beaches in this country",
 *         example=5
 *     ),
 *     @OA\Property(
 *         property="continent",
 *         ref="#/components/schemas/Continent",
 *         description="Continent information (when included)"
 *     )
 * )
 */
class CountrySchema
{
    // This class is used only for Swagger documentation
}
