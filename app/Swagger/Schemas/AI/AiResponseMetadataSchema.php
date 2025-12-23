<?php

namespace App\Swagger\Schemas\AI;

/**
 * @OA\Schema(
 *     schema="AiResponseMetadata",
 *     title="AI Response Metadata",
 *     description="Metadata about the AI response",
 *     type="object",
 *     required={"tokens_used", "model", "response_time_ms", "cached"},
 *     @OA\Property(
 *         property="tokens_used",
 *         type="integer",
 *         description="LLM tokens consumed",
 *         example=1250
 *     ),
 *     @OA\Property(
 *         property="model",
 *         type="string",
 *         description="LLM model used",
 *         example="gpt-3.5-turbo"
 *     ),
 *     @OA\Property(
 *         property="response_time_ms",
 *         type="integer",
 *         description="Response time in milliseconds",
 *         example=850
 *     ),
 *     @OA\Property(
 *         property="cached",
 *         type="boolean",
 *         description="Was response cached?",
 *         example=false
 *     )
 * )
 */
class AiResponseMetadataSchema {}

