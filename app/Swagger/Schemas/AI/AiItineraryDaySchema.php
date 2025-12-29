<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiItineraryDay",
 *     title="AI Itinerary Day",
 *     description="Single day in the itinerary",
 *     type="object",
 *     required={"day_number", "theme", "base_area", "items", "logistics"},
 *     @OA\Property(
 *         property="day_number",
 *         type="integer",
 *         minimum=1,
 *         description="Day number (1-indexed)",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="theme",
 *         type="string",
 *         description="Short theme for the day",
 *         example="Split classics"
 *     ),
 *     @OA\Property(
 *         property="base_area",
 *         type="string",
 *         description="Base area/city for the day",
 *         example="Split"
 *     ),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         minItems=2,
 *         maxItems=6,
 *         description="Activities/beaches for the day (2-6 items)",
 *         @OA\Items(ref="#/components/schemas/AiItineraryItem")
 *     ),
 *     @OA\Property(
 *         property="logistics",
 *         type="object",
 *         description="Logistics information for the day",
 *         @OA\Property(
 *             property="estimated_travel_km",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Estimated travel distance in kilometers",
 *             example=10
 *         ),
 *         @OA\Property(
 *             property="parking_notes",
 *             type="string",
 *             nullable=true,
 *             description="Parking information",
 *             example="Use nearby paid lots if street parking is full."
 *         ),
 *         @OA\Property(
 *             property="accessibility_notes",
 *             type="string",
 *             nullable=true,
 *             description="Accessibility information",
 *             example="All beaches have wheelchair access."
 *         )
 *     )
 * )
 */
class AiItineraryDaySchema {}

