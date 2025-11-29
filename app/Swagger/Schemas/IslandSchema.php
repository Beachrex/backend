<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Island",
 *     type="object",
 *     title="Island",
 *     description="Island model",
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
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches on this island",
 *         example=15
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

