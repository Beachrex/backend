<?php

namespace App\Swagger\Schemas\Payment;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EntitlementsResponse",
 *     type="object",
 *     required={"entitlements", "purchases"},
 *     @OA\Property(
 *         property="entitlements",
 *         type="object",
 *         required={"itinerary_3day_credits", "itinerary_7day_credits", "itinerary_14day_credits"},
 *         @OA\Property(
 *             property="itinerary_3day_credits",
 *             type="integer",
 *             description="Number of 3-day itinerary credits available",
 *             example=2
 *         ),
 *         @OA\Property(
 *             property="itinerary_7day_credits",
 *             type="integer",
 *             description="Number of 7-day itinerary credits available",
 *             example=1
 *         ),
 *         @OA\Property(
 *             property="itinerary_14day_credits",
 *             type="integer",
 *             description="Number of 14-day itinerary credits available",
 *             example=0
 *         )
 *     ),
 *     @OA\Property(
 *         property="purchases",
 *         type="array",
 *         description="User's purchase history",
 *         @OA\Items(ref="#/components/schemas/Purchase")
 *     )
 * )
 */
class EntitlementsResponseSchema
{
}

