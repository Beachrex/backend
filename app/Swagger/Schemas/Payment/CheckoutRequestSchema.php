<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CheckoutRequest",
 *     type="object",
 *     required={"product_type", "accept_terms", "waive_withdrawal"},
 *     @OA\Property(
 *         property="product_type",
 *         type="string",
 *         enum={"itinerary_3day", "itinerary_7day", "itinerary_14day"},
 *         description="Type of product to purchase",
 *         example="itinerary_7day"
 *     ),
 *     @OA\Property(property="accept_terms", type="boolean", example=true, description="The buyer accepts the terms of sale (must be true)"),
 *     @OA\Property(property="waive_withdrawal", type="boolean", example=true, description="The buyer asks for immediate delivery and acknowledges losing the 14-day withdrawal right once a plan is created (must be true)")
 * )
 */
class CheckoutRequestSchema
{
}

