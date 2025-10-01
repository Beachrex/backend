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
