<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="CheckoutResponse",
 *     type="object",
 *     required={"checkout_url", "session_id", "product"},
 *     @OA\Property(
 *         property="checkout_url",
 *         type="string",
 *         format="uri",
 *         description="Stripe Checkout session URL to redirect user to",
 *         example="https://checkout.stripe.com/c/pay/cs_test_..."
 *     ),
 *     @OA\Property(
 *         property="session_id",
 *         type="string",
 *         description="Stripe Checkout session ID",
 *         example="cs_test_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
 *     ),
 *     @OA\Property(
 *         property="product",
 *         ref="#/components/schemas/Product"
 *     )
 * )
 */
class CheckoutResponseSchema
{
}

