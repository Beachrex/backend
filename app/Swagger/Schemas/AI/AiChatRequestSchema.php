<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiChatRequest",
 *     title="AI Chat Request",
 *     description="Request schema for AI chat endpoint",
 *     type="object",
 *     required={"message"},
 *     @OA\Property(
 *         property="message",
 *         type="string",
 *         maxLength=500,
 *         description="User message to send to AI agent",
 *         example="Show me family-friendly beaches in Croatia with parking"
 *     ),
 *     @OA\Property(
 *         property="session_id",
 *         type="string",
 *         format="uuid",
 *         nullable=true,
 *         description="Optional session ID. Auto-generated if not provided",
 *         example="abc123-def456-ghi789"
 *     ),
 *     @OA\Property(
 *         property="context",
 *         type="object",
 *         nullable=true,
 *         description="Optional context for the request",
 *         @OA\Property(
 *             property="location",
 *             type="object",
 *             nullable=true,
 *             description="User's current location",
 *             @OA\Property(
 *                 property="latitude",
 *                 type="number",
 *                 format="float",
 *                 minimum=-90,
 *                 maximum=90,
 *                 description="Latitude coordinate",
 *                 example=45.8150
 *             ),
 *             @OA\Property(
 *                 property="longitude",
 *                 type="number",
 *                 format="float",
 *                 minimum=-180,
 *                 maximum=180,
 *                 description="Longitude coordinate",
 *                 example=15.9819
 *             )
 *         ),
 *         @OA\Property(
 *             property="preferences",
 *             type="object",
 *             nullable=true,
 *             description="User preferences for beach search",
 *             @OA\Property(
 *                 property="max_distance_km",
 *                 type="integer",
 *                 minimum=1,
 *                 maximum=500,
 *                 nullable=true,
 *                 description="Maximum distance from user location in kilometers",
 *                 example=50
 *             ),
 *             @OA\Property(
 *                 property="surface_types",
 *                 type="array",
 *                 nullable=true,
 *                 description="Preferred beach surface types",
 *                 @OA\Items(
 *                     type="string",
 *                     enum={"sand", "pebble", "rock", "concrete"}
 *                 ),
 *                 example={"sand", "pebble"}
 *             ),
 *             @OA\Property(
 *                 property="facilities",
 *                 type="array",
 *                 nullable=true,
 *                 description="Required facilities",
 *                 @OA\Items(type="string"),
 *                 example={"parking", "showers", "playground"}
 *             )
 *         )
 *     ),
 *     @OA\Property(
 *         property="turnstile_token",
 *         type="string",
 *         nullable=true,
 *         maxLength=2048,
 *         description="Cloudflare Turnstile token (widget action `ai_chat`). Needed only when the server has Turnstile on and asks for it (403 turnstile_required / turnstile_failed): browser requests, or every client when TURNSTILE_REQUIRED_FOR=all. One successful check covers the conversation (session_id) for the session lifetime from the same IP.",
 *         example="0.xxxxxxxx"
 *     ),
 *     @OA\Property(
 *         property="page_context",
 *         type="object",
 *         nullable=true,
 *         additionalProperties=false,
 *         description="TASK-010: the website page the chat widget is open on. Optional, an untrusted hint only (lets the assistant understand 'this beach' / 'here'); never used for authorization. For page_type=beach the server resolves the beach name and place from beach_id (active beaches only) and ignores title. Other keys → 422.",
 *         required={"page_type"},
 *         @OA\Property(property="page_type", type="string", enum={"home", "beach", "destination", "country", "region", "island", "city", "search", "map", "article", "other"}, example="beach"),
 *         @OA\Property(property="path", type="string", maxLength=300, nullable=true, description="Page path without query string: starts with /, only letters, digits and - . _ ~ / % (URL-encoded)", example="/en/beaches/europe/croatia/split-dalmatia/split/bacvice"),
 *         @OA\Property(property="beach_id", type="integer", minimum=1, nullable=true, description="Beach id on beach pages", example=989),
 *         @OA\Property(property="title", type="string", maxLength=120, nullable=true, description="Page heading (not used for beach pages; cleaned and cut to 80 characters before it reaches the model)", example="Split")
 *     )
 * )
 */
class AiChatRequestSchema {}

