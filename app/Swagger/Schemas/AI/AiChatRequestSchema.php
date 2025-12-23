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
 *     )
 * )
 */
class AiChatRequestSchema {}

