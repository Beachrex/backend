<?php

namespace App\Listeners\AI;

use App\Events\AI\AiRequestCompleted;
use App\Events\AI\AiRequestFailed;
use Illuminate\Support\Facades\Cache;

/**
 * Listener for tracking AI metrics.
 *
 * Tracks real-time metrics in cache for monitoring dashboards.
 *
 * Metrics tracked:
 * - Total requests (success + failure)
 * - Success count
 * - Error count by code (400, 402, 413, 429, 503, 500)
 * - Average latency
 * - Average cost
 * - Total cost (daily)
 *
 * Cache keys:
 * - ai:metrics:requests:total:{date}
 * - ai:metrics:requests:success:{date}
 * - ai:metrics:requests:error:{code}:{date}
 * - ai:metrics:latency:sum:{date}
 * - ai:metrics:latency:count:{date}
 * - ai:metrics:cost:sum:{date}
 *
 * TTL: 7 days (for historical data)
 *
 * Source: docs/ai/implementation/STEP6_COMPLETION_REPORT.md
 */
class TrackAiMetrics
{
    /**
     * Handle AiRequestCompleted event.
     */
    public function handleCompleted(AiRequestCompleted $event): void
    {
        $date = now()->format('Y-m-d');
        $ttl = 60 * 60 * 24 * 7; // 7 days

        // Increment total requests
        $total = Cache::get("ai:metrics:requests:total:{$date}", 0);
        Cache::put("ai:metrics:requests:total:{$date}", $total + 1, $ttl);

        // Increment success count
        $success = Cache::get("ai:metrics:requests:success:{$date}", 0);
        Cache::put("ai:metrics:requests:success:{$date}", $success + 1, $ttl);

        // Track latency (for average calculation)
        $latencySum = Cache::get("ai:metrics:latency:sum:{$date}", 0);
        Cache::put("ai:metrics:latency:sum:{$date}", $latencySum + $event->latencyMs, $ttl);

        $latencyCount = Cache::get("ai:metrics:latency:count:{$date}", 0);
        Cache::put("ai:metrics:latency:count:{$date}", $latencyCount + 1, $ttl);

        // Track cost
        $costSum = Cache::get("ai:metrics:cost:sum:{$date}", 0.0);
        Cache::put("ai:metrics:cost:sum:{$date}", $costSum + $event->costUsd, $ttl);

        // Track by tier
        $tierCount = Cache::get("ai:metrics:requests:tier:{$event->tier}:{$date}", 0);
        Cache::put("ai:metrics:requests:tier:{$event->tier}:{$date}", $tierCount + 1, $ttl);
    }

    /**
     * Handle AiRequestFailed event.
     */
    public function handleFailed(AiRequestFailed $event): void
    {
        $date = now()->format('Y-m-d');
        $ttl = 60 * 60 * 24 * 7; // 7 days

        // Increment total requests
        $total = Cache::get("ai:metrics:requests:total:{$date}", 0);
        Cache::put("ai:metrics:requests:total:{$date}", $total + 1, $ttl);

        // Increment error count by HTTP status
        $errorByStatus = Cache::get("ai:metrics:requests:error:{$event->httpStatus}:{$date}", 0);
        Cache::put("ai:metrics:requests:error:{$event->httpStatus}:{$date}", $errorByStatus + 1, $ttl);

        // Increment error count by error code
        $errorByCode = Cache::get("ai:metrics:requests:error_code:{$event->errorCode}:{$date}", 0);
        Cache::put("ai:metrics:requests:error_code:{$event->errorCode}:{$date}", $errorByCode + 1, $ttl);

        // Track by tier
        $tierCount = Cache::get("ai:metrics:requests:tier:{$event->tier}:{$date}", 0);
        Cache::put("ai:metrics:requests:tier:{$event->tier}:{$date}", $tierCount + 1, $ttl);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            AiRequestCompleted::class => 'handleCompleted',
            AiRequestFailed::class => 'handleFailed',
        ];
    }
}

