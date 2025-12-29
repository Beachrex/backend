<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiItineraryRequest",
 *     title="AI Itinerary Request",
 *     description="Request schema for generating multi-day beach itinerary",
 *     type="object",
 *     required={"destination", "duration_days"},
 *     @OA\Property(
 *         property="destination",
 *         type="object",
 *         required={"country_code"},
 *         description="Destination for the itinerary",
 *         @OA\Property(
 *             property="country_code",
 *             type="string",
 *             minLength=2,
 *             maxLength=2,
 *             description="ISO 3166-1 alpha-2 country code",
 *             example="HR"
 *         ),
 *         @OA\Property(
 *             property="region_slug",
 *             type="string",
 *             nullable=true,
 *             description="Optional region slug",
 *             example="dalmatia"
 *         ),
 *         @OA\Property(
 *             property="city_slug",
 *             type="string",
 *             nullable=true,
 *             description="Optional city slug",
 *             example="split"
 *         )
 *     ),
 *     @OA\Property(
 *         property="duration_days",
 *         type="integer",
 *         enum={3, 7, 14},
 *         description="Trip duration in days (3, 7, or 14 only)",
 *         example=7
 *     ),
 *     @OA\Property(
 *         property="start_date",
 *         type="string",
 *         format="date",
 *         nullable=true,
 *         description="Optional start date (YYYY-MM-DD). If omitted, itinerary is date-agnostic",
 *         example="2025-07-15"
 *     ),
 *     @OA\Property(
 *         property="travel_party",
 *         type="object",
 *         nullable=true,
 *         description="Travel party composition",
 *         @OA\Property(
 *             property="adults",
 *             type="integer",
 *             minimum=1,
 *             maximum=20,
 *             description="Number of adults",
 *             example=2
 *         ),
 *         @OA\Property(
 *             property="children",
 *             type="integer",
 *             minimum=0,
 *             maximum=20,
 *             description="Number of children",
 *             example=0
 *         )
 *     ),
 *     @OA\Property(
 *         property="preferences",
 *         type="object",
 *         nullable=true,
 *         description="User preferences for the itinerary",
 *         @OA\Property(
 *             property="pace",
 *             type="string",
 *             enum={"relaxed", "balanced", "packed"},
 *             description="Trip pace",
 *             example="balanced"
 *         ),
 *         @OA\Property(
 *             property="interests",
 *             type="array",
 *             maxItems=10,
 *             description="User interests (max 10)",
 *             @OA\Items(
 *                 type="string",
 *                 enum={"swimming", "snorkeling", "family", "quiet", "nightlife", "nature", "diving", "surfing", "kayaking", "hiking"}
 *             ),
 *             example={"swimming", "family"}
 *         ),
 *         @OA\Property(
 *             property="must_have_facilities",
 *             type="array",
 *             maxItems=10,
 *             description="Required facilities (max 10)",
 *             @OA\Items(type="string"),
 *             example={"parking", "showers"}
 *         ),
 *         @OA\Property(
 *             property="surface_types",
 *             type="array",
 *             maxItems=10,
 *             description="Preferred beach surface types (max 10)",
 *             @OA\Items(
 *                 type="string",
 *                 enum={"sand", "pebble", "rock", "concrete", "grass"}
 *             ),
 *             example={"pebble", "sand"}
 *         ),
 *         @OA\Property(
 *             property="accessibility",
 *             type="array",
 *             maxItems=10,
 *             description="Accessibility requirements (max 10)",
 *             @OA\Items(
 *                 type="string",
 *                 enum={"wheelchair_accessible", "ramp_available", "accessible_parking", "accessible_toilets"}
 *             ),
 *             example={"wheelchair_accessible"}
 *         )
 *     ),
 *     @OA\Property(
 *         property="constraints",
 *         type="object",
 *         nullable=true,
 *         description="Travel constraints",
 *         @OA\Property(
 *             property="max_travel_km_per_day",
 *             type="integer",
 *             minimum=10,
 *             maximum=500,
 *             description="Maximum travel distance per day in kilometers",
 *             example=60
 *         ),
 *         @OA\Property(
 *             property="avoid_ferries",
 *             type="boolean",
 *             description="Avoid ferry routes",
 *             example=false
 *         )
 *     ),
 *     @OA\Property(
 *         property="locale",
 *         type="string",
 *         minLength=2,
 *         maxLength=2,
 *         nullable=true,
 *         description="Preferred language for itinerary (ISO 639-1). If missing, uses Accept-Language header",
 *         example="en"
 *     ),
 *     @OA\Property(
 *         property="session_id",
 *         type="string",
 *         format="uuid",
 *         nullable=true,
 *         description="Optional session ID for continuity with AI chat session",
 *         example="019b6be2-2aca-72e2-abae-4b640ac6f25b"
 *     )
 * )
 */
class AiItineraryRequestSchema {}

