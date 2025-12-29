<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiItineraryResponse",
 *     title="AI Itinerary Response",
 *     description="Successful response from itinerary generation endpoint",
 *     type="object",
 *     required={"itinerary_id", "title", "summary", "destination", "duration_days", "days", "assumptions", "metadata"},
 *     @OA\Property(
 *         property="itinerary_id",
 *         type="string",
 *         format="uuid",
 *         description="Generated itinerary ID for tracking and caching",
 *         example="3bfb6fd6-1d18-4a19-8f0d-3f4f7c1a0a0c"
 *     ),
 *     @OA\Property(
 *         property="session_id",
 *         type="string",
 *         format="uuid",
 *         nullable=true,
 *         description="Session ID if linked to chat session",
 *         example="019b6be2-2aca-72e2-abae-4b640ac6f25b"
 *     ),
 *     @OA\Property(
 *         property="title",
 *         type="string",
 *         description="Localized itinerary title",
 *         example="7-Day Beach Itinerary - Dalmatia, Croatia"
 *     ),
 *     @OA\Property(
 *         property="summary",
 *         type="string",
 *         description="Brief summary (2-5 sentences)",
 *         example="A balanced week around Split and nearby islands, mixing popular beaches with quieter coves."
 *     ),
 *     @OA\Property(
 *         property="destination",
 *         type="object",
 *         description="Destination details",
 *         @OA\Property(
 *             property="country_code",
 *             type="string",
 *             example="HR"
 *         ),
 *         @OA\Property(
 *             property="region_slug",
 *             type="string",
 *             nullable=true,
 *             example="dalmatia"
 *         ),
 *         @OA\Property(
 *             property="city_slug",
 *             type="string",
 *             nullable=true,
 *             example="split"
 *         )
 *     ),
 *     @OA\Property(
 *         property="duration_days",
 *         type="integer",
 *         enum={3, 7, 14},
 *         description="Trip duration in days",
 *         example=7
 *     ),
 *     @OA\Property(
 *         property="days",
 *         type="array",
 *         description="Day-by-day itinerary (length = duration_days)",
 *         @OA\Items(ref="#/components/schemas/AiItineraryDay")
 *     ),
 *     @OA\Property(
 *         property="assumptions",
 *         type="array",
 *         description="Transparent statements about data sources and ordering",
 *         @OA\Items(type="string"),
 *         example={"Beach ordering uses visitor interest (beach_views) when no explicit location routing is provided."}
 *     ),
 *     @OA\Property(
 *         property="metadata",
 *         type="object",
 *         description="Response metadata",
 *         required={"request_id", "tier", "tokens_used", "model", "response_time_ms", "cached"},
 *         @OA\Property(
 *             property="request_id",
 *             type="string",
 *             format="uuid",
 *             description="Unique request identifier",
 *             example="94f27541-491e-49ba-89ac-e8af24eaafaa"
 *         ),
 *         @OA\Property(
 *             property="tier",
 *             type="string",
 *             description="User tier",
 *             example="basic"
 *         ),
 *         @OA\Property(
 *             property="tokens_used",
 *             type="integer",
 *             description="LLM tokens consumed",
 *             example=1820
 *         ),
 *         @OA\Property(
 *             property="model",
 *             type="string",
 *             description="LLM model used",
 *             example="gpt-3.5-turbo"
 *         ),
 *         @OA\Property(
 *             property="response_time_ms",
 *             type="integer",
 *             description="Response time in milliseconds",
 *             example=4100
 *         ),
 *         @OA\Property(
 *             property="cached",
 *             type="boolean",
 *             description="Was response cached?",
 *             example=false
 *         )
 *     )
 * )
 */
class AiItineraryResponseSchema {}

