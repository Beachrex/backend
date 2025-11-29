<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Region",
 *     type="object",
 *     title="Region",
 *     description="Region model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Region ID",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=100,
 *         description="Region code",
 *         example="istria"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Region name",
 *         example="Istria"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         description="Localized URL-friendly slug",
 *         example="istria"
 *     ),
 *     @OA\Property(
 *         property="country_code",
 *         type="string",
 *         maxLength=2,
 *         description="Two-letter country code",
 *         example="HR"
 *     ),
 *     @OA\Property(
 *         property="latitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="Region latitude (centroid of beaches)",
 *         example=43.5147
 *     ),
 *     @OA\Property(
 *         property="longitude",
 *         type="number",
 *         format="double",
 *         nullable=true,
 *         description="Region longitude (centroid of beaches)",
 *         example=16.4435
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
 *         description="Number of beaches in this region",
 *         example=25
 *     ),
 *     @OA\Property(
 *         property="accommodation_count",
 *         type="integer",
 *         description="Number of accommodations in this region (hardcoded to 0 until booking integration)",
 *         example=0
 *     ),
 *     @OA\Property(
 *         property="country",
 *         ref="#/components/schemas/Country",
 *         description="Country information (when included)"
 *     ),
 *     @OA\Property(
 *         property="badges",
 *         type="array",
 *         description="Badges associated with this region (when included)",
 *         @OA\Items(ref="#/components/schemas/Badge")
 *     ),
 *     @OA\Property(
 *         property="articles",
 *         type="array",
 *         description="Articles associated with this region (when included)",
 *         @OA\Items(ref="#/components/schemas/Article")
 *     )
 * )
 */
class RegionSchema
{
    // This class is used only for Swagger documentation
}

