<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AiUsage;

/**
 * Rate Limiting Middleware for AI Endpoints
 * 
 * Enforces rate limits based on tier:
 * - Anonymous (free tier): 100 requests/hour per IP
 * - Authenticated (paid tier): 500 requests/hour per user
 * 
 * Returns 429 Too Many Requests when limit exceeded.
 * 
 * Source: docs/ai/architecture/api-contracts.md Section 3.3
 */
class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tier = $this->determineTier($user);
        
        // Get rate limit configuration for tier
        $config = config("ai.tiers.{$tier}.rate_limit");
        $limit = $config['requests_per_hour'];
        
        // Determine rate limit key (IP for anonymous, user ID for authenticated)
        $key = $this->getRateLimitKey($request, $user);
        
        // Check rate limit
        $executed = RateLimiter::attempt(
            $key,
            $limit,
            function() {},
            3600 // 1 hour in seconds
        );
        
        if (!$executed) {
            // Rate limit exceeded
            $availableAt = RateLimiter::availableIn($key);
            $resetAt = now()->addSeconds($availableAt);
            
            return response()->json([
                'error' => 'rate_limit_exceeded',
                'message' => $tier === 'free' 
                    ? 'You have exceeded the rate limit for free tier'
                    : 'You have exceeded the rate limit for your tier',
                'limit' => $limit,
                'reset_at' => $resetAt->toIso8601String(),
                'upgrade_url' => 'https://beachrex.com/pricing',
            ], 429);
        }
        
        // Add rate limit headers to response
        $response = $next($request);
        
        $remaining = RateLimiter::remaining($key, $limit);
        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', $remaining);
        
        return $response;
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
    
    /**
     * Get rate limit key for request.
     * 
     * - Anonymous: IP address (hashed for privacy)
     * - Authenticated: User ID
     */
    private function getRateLimitKey(Request $request, $user): string
    {
        if ($user) {
            return "ai:rate_limit:user:{$user->id}";
        }
        
        // Hash IP address for privacy (GDPR compliance)
        $ip = $request->ip();
        $hashedIp = hash('sha256', $ip . config('app.key'));
        
        return "ai:rate_limit:ip:{$hashedIp}";
    }
}

