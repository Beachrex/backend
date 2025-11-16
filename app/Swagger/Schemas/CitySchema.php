<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="City",
 *     type="object",
 *     title="City",
 *     description="City model",
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
 *         description="Two-letter country code",
 *         example="HR"
 *     ),
 *     @OA\Property(
 *         property="region_id",
 *         type="integer",
 *         nullable=true,
 *         description="Region ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches in this city",
 *         example=10
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

