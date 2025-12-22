<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AiUsage;

/**
 * Circuit Breaker Middleware for AI Endpoints
 * 
 * Prevents runaway costs by disabling AI service when daily cost limits are exceeded:
 * - Free tier daily limit: $500 USD → Disable free tier only
 * - Emergency shutdown limit: $5,000 USD → Disable all tiers
 * 
 * Returns 503 Service Unavailable when circuit breaker is triggered.
 * 
 * Source: docs/ai/architecture/api-contracts.md Section 3.4
 */
class CircuitBreakerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if AI service is enabled
        if (!config('ai.features.enabled')) {
            return response()->json([
                'error' => 'service_unavailable',
                'message' => 'AI service is temporarily disabled',
                'retry_after' => null,
            ], 503);
        }
        
        $user = $request->user();
        $tier = $this->determineTier($user);
        
        // Get circuit breaker limits
        $freeTierLimit = config('ai.circuit_breakers.free_tier_daily_limit');
        $emergencyLimit = config('ai.circuit_breakers.emergency_shutdown_limit');
        
        // Check emergency shutdown (all tiers)
        $totalDailyCost = $this->getDailyCost();
        
        if ($totalDailyCost >= $emergencyLimit) {
            return response()->json([
                'error' => 'service_unavailable',
                'message' => 'AI service is temporarily unavailable due to high demand',
                'retry_after' => $this->getRetryAfter(),
            ], 503);
        }
        
        // Check free tier limit (free tier only)
        if ($tier === 'free') {
            $freeTierDailyCost = $this->getDailyCost('free');
            
            if ($freeTierDailyCost >= $freeTierLimit) {
                return response()->json([
                    'error' => 'service_unavailable',
                    'message' => 'Free tier AI service is temporarily unavailable. Please try again tomorrow or upgrade to paid tier.',
                    'retry_after' => $this->getRetryAfter(),
                    'upgrade_url' => 'https://beachrex.com/pricing',
                ], 503);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Get total daily cost for AI usage.
     * 
     * @param string|null $tier Filter by tier (null = all tiers)
     * @return float Total cost in USD
     */
    private function getDailyCost(?string $tier = null): float
    {
        // Cache key for daily cost
        $cacheKey = $tier 
            ? "ai:circuit_breaker:daily_cost:{$tier}:" . now()->format('Y-m-d')
            : "ai:circuit_breaker:daily_cost:all:" . now()->format('Y-m-d');
        
        // Cache for 5 minutes to reduce DB queries
        return Cache::remember($cacheKey, 300, function () use ($tier) {
            $query = AiUsage::whereDate('created_at', today());
            
            if ($tier) {
                $query->where('tier', $tier);
            }
            
            return (float) $query->sum('cost_usd');
        });
    }
    
    /**
     * Get retry-after timestamp (next day at midnight UTC).
     * 
     * @return string ISO 8601 timestamp
     */
    private function getRetryAfter(): string
    {
        return now()->addDay()->startOfDay()->toIso8601String();
    }
    
    /**
     * Determine user's tier.
     * 
     * For MVP: All authenticated users are 'free' tier until payment system is implemented.
     * Anonymous users are always 'free' tier.
     */
    private function determineTier($user): string
    {
        if (!$user) {
            return 'free';
        }
        
        // TODO: Once payment system is implemented, check user's actual tier
        // For now, all authenticated users are 'free' tier
        // Future: return $user->ai_tier ?? 'free';
        
        return 'free';
    }
}

