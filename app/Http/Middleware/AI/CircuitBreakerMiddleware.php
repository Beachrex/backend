<?php

namespace App\Http\Middleware\AI;

use App\Services\AI\AiDailySpend;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Daily cost circuit breaker for the AI endpoints (ADR 2025-12-22, limits changed by the owner on 2026-10-29).
 *
 * - Free AI chat: off when today's free usage reaches `ai.circuit_breakers.free_tier_daily_limit` (default $5).
 * - Everything (also the paid trip planner): off at `emergency_shutdown_limit` (default $20).
 * - "Today" is the local day in `ai.circuit_breakers.timezone`; at the next midnight it is open again by itself.
 *
 * A tripped breaker answers 503 with `reason: "daily_limit"` and `retry_after` (next local midnight, ISO 8601),
 * which the website shows as "the assistant is resting until tomorrow".
 *
 * Route parameter: `chat` (default) checks both limits; `trip_planner` (paid, bought credits) only the emergency
 * limit, so a busy free chat never blocks a plan someone paid for.
 */
class CircuitBreakerMiddleware
{
    public function handle(Request $request, Closure $next, string $scope = 'chat'): Response
    {
        if (! config('ai.features.enabled')) {
            return response()->json([
                'error' => 'service_unavailable',
                'message' => 'AI service is temporarily disabled',
                'retry_after' => null,
            ], 503);
        }

        $emergencyLimit = (float) config('ai.circuit_breakers.emergency_shutdown_limit');
        $total = AiDailySpend::cost(AiDailySpend::BUCKET_ALL);
        if ($total >= $emergencyLimit) {
            AiDailySpend::tripped(AiDailySpend::BUCKET_ALL, $total, $emergencyLimit);

            return $this->limitReached('The AI assistant has reached its daily limit and is resting until tomorrow.');
        }

        // Every visitor uses the free chat today (PlanEnforcementService::determineTier): no paid chat tier yet.
        if ($scope === 'chat') {
            $freeLimit = (float) config('ai.circuit_breakers.free_tier_daily_limit');
            $free = AiDailySpend::cost(AiDailySpend::BUCKET_FREE);
            if ($free >= $freeLimit) {
                AiDailySpend::tripped(AiDailySpend::BUCKET_FREE, $free, $freeLimit);

                return $this->limitReached('The AI assistant has reached its daily limit and is resting until tomorrow.');
            }
        }

        return $next($request);
    }

    private function limitReached(string $message): JsonResponse
    {
        $resetsAt = AiDailySpend::resetsAt();

        return response()->json([
            'error' => 'service_unavailable',
            'reason' => 'daily_limit',
            'message' => $message,
            'retry_after' => $resetsAt->toIso8601String(),
        ], 503, ['Retry-After' => (string) max(60, $resetsAt->getTimestamp() - time())]);
    }
}
