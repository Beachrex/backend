<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Purchase",
 *     type="object",
 *     required={"id", "product_type", "amount", "currency", "status"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         description="Purchase ID",
 *         example=123
 *     ),
 *     @OA\Property(
 *         property="product_type",
 *         type="string",
 *         description="Product type purchased",
 *         example="itinerary_7day"
 *     ),
 *     @OA\Property(
 *         property="amount",
 *         type="number",
 *         format="float",
 *         description="Purchase amount",
 *         example=39.00
 *     ),
 *     @OA\Property(
 *         property="currency",
 *         type="string",
 *         description="ISO 4217 currency code",
 *         example="USD"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"pending", "completed", "failed", "refunded"},
 *         description="Purchase status",
 *         example="completed"
 *     ),
 *     @OA\Property(
 *         property="purchased_at",
 *         type="string",
 *         format="date-time",
 *         nullable=true,
 *         description="ISO 8601 timestamp of purchase completion",
 *         example="2025-12-30T14:30:00Z"
 *     ),
 *     @OA\Property(
 *         property="stripe_session_id",
 *         type="string",
 *         nullable=true,
 *         description="Stripe Checkout session ID",
 *         example="cs_test_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
 *     )
 * )
 */
class PurchaseSchema
{
}

