<?php

namespace App\Events\AI;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an AI request fails.
 * 
 * Used for error tracking and monitoring.
 * 
 * Metrics to track:
 * - Error count by code
 * - 402 count (payment required)
 * - 429 count (rate limit)
 * - 503 count (circuit breaker)
 * - 413 count (token budget)
 * 
 * Source: docs/ai/implementation/STEP6_COMPLETION_REPORT.md
 */
class AiRequestFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $requestId,
        public readonly ?string $sessionId,
        public readonly string $tier,
        public readonly int $httpStatus,
        public readonly string $errorCode,
        public readonly ?string $errorMessage = null,
    ) {}
}

