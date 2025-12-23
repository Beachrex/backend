<?php

namespace App\Events\AI;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an AI request completes successfully.
 * 
 * Used for metrics collection and monitoring.
 * 
 * Metrics to track:
 * - Request count
 * - Average latency
 * - Average cost
 * - Token usage
 * - Context truncation rate
 * 
 * Source: docs/ai/implementation/STEP6_COMPLETION_REPORT.md
 */
class AiRequestCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $sessionId,
        public readonly string $tier,
        public readonly string $locale,
        public readonly int $latencyMs,
        public readonly int $tokensInput,
        public readonly int $tokensOutput,
        public readonly int $tokensTotal,
        public readonly float $costUsd,
        public readonly bool $contextTruncated,
        public readonly int $beachesFetched,
        public readonly int $beachesReturned,
        public readonly string $intent,
        public readonly string $llmProvider,
        public readonly string $llmModel,
    ) {}
}

