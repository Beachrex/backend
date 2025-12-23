<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiChatResponse",
 *     title="AI Chat Response",
 *     description="Successful response from AI chat endpoint",
 *     type="object",
 *     required={"session_id", "message", "intent", "metadata"},
 *     @OA\Property(
 *         property="session_id",
 *         type="string",
 *         format="uuid",
 *         description="Session identifier",
 *         example="abc123-def456-ghi789"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         description="AI response message",
 *         example="I found 15 family-friendly beaches in Croatia with parking. Here are the top 5 matches:"
 *     ),
 *     @OA\Property(
 *         property="intent",
 *         type="string",
 *         description="Detected user intent",
 *         enum={"beach_search", "beach_comparison", "weather_query", "itinerary_request", "accessibility_query", "general_question", "unknown"},
 *         example="beach_search"
 *     ),
 *     @OA\Property(
 *         property="beaches",
 *         type="array",
 *         nullable=true,
 *         description="Beach results (if applicable)",
 *         @OA\Items(ref="#/components/schemas/AiBeachRecommendation")
 *     ),
 *     @OA\Property(
 *         property="filters_applied",
 *         type="object",
 *         nullable=true,
 *         description="Filters used in search",
 *         @OA\Property(property="country", type="string", nullable=true, example="HR"),
 *         @OA\Property(property="region", type="string", nullable=true, example="Dalmatia"),
 *         @OA\Property(property="city", type="string", nullable=true, example="Split"),
 *         @OA\Property(
 *             property="surface",
 *             type="array",
 *             nullable=true,
 *             @OA\Items(type="string"),
 *             example={"sand", "pebble"}
 *         ),
 *         @OA\Property(
 *             property="facilities",
 *             type="array",
 *             nullable=true,
 *             @OA\Items(type="string"),
 *             example={"parking"}
 *         ),
 *         @OA\Property(property="family_friendly", type="boolean", nullable=true, example=true),
 *         @OA\Property(property="wheelchair_accessible", type="boolean", nullable=true, example=false)
 *     ),
 *     @OA\Property(
 *         property="suggestions",
 *         type="array",
 *         nullable=true,
 *         description="Follow-up suggestions",
 *         @OA\Items(type="string"),
 *         example={"Show me beaches with playgrounds", "Which one has the best weather today?", "Compare the top 3 beaches"}
 *     ),
 *     @OA\Property(
 *         property="metadata",
 *         ref="#/components/schemas/AiResponseMetadata",
 *         description="Response metadata"
 *     )
 * )
 */
class AiChatResponseSchema {}

