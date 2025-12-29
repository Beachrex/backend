<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiItineraryItem",
 *     title="AI Itinerary Item",
 *     description="Single item (beach/activity) in a day's itinerary",
 *     type="object",
 *     required={"type", "time_block", "notes", "why"},
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"beach", "activity", "food", "viewpoint"},
 *         description="Item type",
 *         example="beach"
 *     ),
 *     @OA\Property(
 *         property="time_block",
 *         type="string",
 *         enum={"morning", "afternoon", "evening"},
 *         description="Time block for this item",
 *         example="morning"
 *     ),
 *     @OA\Property(
 *         property="beach",
 *         type="object",
 *         nullable=true,
 *         description="Beach details (required if type=beach, null otherwise)",
 *         @OA\Property(
 *             property="id",
 *             type="integer",
 *             description="Beach ID",
 *             example=614
 *         ),
 *         @OA\Property(
 *             property="name",
 *             type="string",
 *             description="Beach name",
 *             example="Beach Bacvice"
 *         ),
 *         @OA\Property(
 *             property="slug",
 *             type="string",
 *             description="Beach slug",
 *             example="beach-bacvice"
 *         ),
 *         @OA\Property(
 *             property="city",
 *             type="string",
 *             nullable=true,
 *             description="City name",
 *             example="Split"
 *         ),
 *         @OA\Property(
 *             property="country_code",
 *             type="string",
 *             description="ISO 3166-1 alpha-2 country code",
 *             example="HR"
 *         ),
 *         @OA\Property(
 *             property="surface",
 *             type="string",
 *             nullable=true,
 *             description="Beach surface type",
 *             example="sand"
 *         ),
 *         @OA\Property(
 *             property="facilities",
 *             type="array",
 *             description="Available facilities",
 *             @OA\Items(type="string"),
 *             example={"parking", "showers"}
 *         ),
 *         @OA\Property(
 *             property="distance_km",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Distance from user location (usually null for itineraries)",
 *             example=null
 *         )
 *     ),
 *     @OA\Property(
 *         property="notes",
 *         type="string",
 *         description="Notes for this item (1-3 sentences)",
 *         example="Arrive early for easier parking and calmer water."
 *     ),
 *     @OA\Property(
 *         property="why",
 *         type="string",
 *         description="Short justification (must not claim ratings/reviews unless available)",
 *         example="Good facilities and easy access for a first day."
 *     )
 * )
 */
class AiItineraryItemSchema {}

