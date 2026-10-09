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
 *         property="format",
 *         type="string",
 *         enum={"text","markdown"},
 *         description="markdown when the tool-calling agent answered (AI_CHAT_ENGINE=agent): beach links as [name](url) to the website; absent/text otherwise"
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
 *         description="Filters the API actually queried (TASK-003), named like the /beaches query parameters. Relaxed filters are left out and explained in metadata.assumptions. On the keyword fallback (metadata.understanding = fallback) this is the model's own summary.",
 *         @OA\Property(property="country_code", type="string", nullable=true, example="HR"),
 *         @OA\Property(property="island_code", type="string", nullable=true, example="BRA"),
 *         @OA\Property(property="city_code", type="string", nullable=true, example="makarska"),
 *         @OA\Property(property="region_code", type="string", nullable=true, example="HR-ISTRIA"),
 *         @OA\Property(property="beach_type", type="array", nullable=true, @OA\Items(type="string"), example={"sand"}),
 *         @OA\Property(property="location_infrastructure", type="array", nullable=true, @OA\Items(type="string"), example={"shower", "access_for_disabled"}),
 *         @OA\Property(property="user_type", type="array", nullable=true, @OA\Items(type="string"), example={"family"}),
 *         @OA\Property(property="sporting_activities", type="array", nullable=true, @OA\Items(type="string"), example={"snorkeling"})
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

