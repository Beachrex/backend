<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CheckoutRequest",
 *     type="object",
 *     required={"product_type"},
 *     @OA\Property(
 *         property="product_type",
 *         type="string",
 *         enum={"itinerary_3day", "itinerary_7day", "itinerary_14day"},
 *         description="Type of product to purchase",
 *         example="itinerary_7day"
 *     )
 * )
 */
class CheckoutRequestSchema
{
}

