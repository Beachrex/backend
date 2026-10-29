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
 *         example="gpt-4o-mini"
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
 *     ),
 *     @OA\Property(
 *         property="understanding",
 *         type="string",
 *         enum={"llm", "fallback"},
 *         description="Chat only (TASK-003): how the message was understood. llm = structured extraction; fallback = English keyword detection after the extraction failed.",
 *         example="llm"
 *     ),
 *     @OA\Property(
 *         property="language",
 *         type="string",
 *         description="Chat only: language of the answer (the user's language as detected, else the session locale).",
 *         example="sl"
 *     ),
 *     @OA\Property(
 *         property="assumptions",
 *         type="array",
 *         description="Chat only: what the API could not apply (unknown place, relaxed filters).",
 *         @OA\Items(type="string"),
 *         example={"Not enough beaches matched all requested user_types; that filter was relaxed."}
 *     ),
 *     @OA\Property(
 *         property="page_context_used",
 *         type="boolean",
 *         description="Chat only (TASK-010): true when a valid page_context was sent and turned into a page hint for the model.",
 *         example=true
 *     )
 * )
 */
class AiResponseMetadataSchema {}

