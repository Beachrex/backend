<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     required={"type", "price", "currency", "credits", "name"},
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         description="Product type identifier",
 *         example="itinerary_7day"
 *     ),
 *     @OA\Property(
 *         property="price",
 *         type="number",
 *         format="float",
 *         description="Price in the specified currency",
 *         example=39.00
 *     ),
 *     @OA\Property(
 *         property="currency",
 *         type="string",
 *         description="ISO 4217 currency code",
 *         example="USD"
 *     ),
 *     @OA\Property(
 *         property="credits",
 *         type="integer",
 *         description="Number of credits granted upon purchase",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Human-readable product name",
 *         example="7-Day Beach Itinerary"
 *     )
 * )
 */
class ProductSchema
{
}

