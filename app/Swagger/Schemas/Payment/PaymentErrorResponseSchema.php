<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PaymentErrorResponse",
 *     type="object",
 *     required={"error", "message"},
 *     @OA\Property(
 *         property="error",
 *         type="string",
 *         description="Error code",
 *         example="invalid_product"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="Human-readable error message",
 *         example="Invalid product type. Must be one of: itinerary_3day, itinerary_7day, itinerary_14day"
 *     ),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         nullable=true,
 *         description="Validation errors (if applicable)",
 *         additionalProperties=true
 *     )
 * )
 */
class PaymentErrorResponseSchema
{
}

