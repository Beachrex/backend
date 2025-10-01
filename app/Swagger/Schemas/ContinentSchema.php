<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="Continent",
 *     type="object",
 *     title="Continent",
 *     description="Continent model",
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         maxLength=2,
 *         description="Two-letter continent code",
 *         example="EU"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Continent name",
 *         example="Europe"
 *     ),
 *     @OA\Property(
 *         property="beaches_count",
 *         type="integer",
 *         description="Number of beaches in this continent",
 *         example=15
 *     )
 * )
 */
class ContinentSchema
{
    // This class is used only for Swagger documentation
}
