<?php

namespace App\Http\Middleware\AI;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AiSession;

/**
 * Token Budget Middleware for AI Endpoints
 * 
 * Enforces token budget limits based on tier:
 * - Free tier: 4,000 tokens per request, 16,000 per session
 * - Basic tier: 16,000 tokens per request, 64,000 per session
 * - Premium tier: 32,000 tokens per request, 128,000 per session
 * - Enterprise tier: 64,000 tokens per request, 256,000 per session
 * 
 * Returns 413 Payload Too Large when budget exceeded.
 * 
 * Source: docs/ai/architecture/api-contracts.md Section 3.3
 */
class TokenBudgetMiddleware
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
        
        // Get token budget configuration for tier
        $config = config("ai.tiers.{$tier}.token_budget");
        $maxTokensPerRequest = $config['max_tokens_per_request'];
        $maxTokensPerSession = $config['max_tokens_per_session'];
        
        // Get or create session
        $sessionId = $request->header('X-Session-ID');
        $session = null;
        
        if ($sessionId) {
            $session = AiSession::find($sessionId);
        }
        
        // Check session token budget (if session exists)
        if ($session && $session->tokens_used >= $maxTokensPerSession) {
            return response()->json([
                'error' => 'token_budget_exceeded',
                'message' => 'You have exceeded the token budget for this session',
                'tokens_used' => $session->tokens_used,
                'max_tokens_per_session' => $maxTokensPerSession,
                'suggestion' => $tier === 'free' 
                    ? 'Start a new session or upgrade to paid tier for higher limits'
                    : 'Start a new session to continue',
            ], 413);
        }
        
        // Estimate input tokens (rough estimate: 1 token ≈ 4 characters)
        $message = $request->input('message', '');
        $estimatedInputTokens = (int) ceil(strlen($message) / 4);
        
        // Check if estimated tokens exceed per-request budget
        // Note: This is a rough estimate. Actual token count will be checked by LLM service.
        if ($estimatedInputTokens > $maxTokensPerRequest) {
            return response()->json([
                'error' => 'token_budget_exceeded',
                'message' => $tier === 'free'
                    ? 'Your query is too complex for the free tier'
                    : 'Your query is too complex for your tier',
                'estimated_tokens' => $estimatedInputTokens,
                'max_tokens' => $maxTokensPerRequest,
                'suggestion' => 'Simplify your query or upgrade to paid tier',
            ], 413);
        }
        
        // Store tier and budget info in request for later use
        $request->attributes->set('ai_tier', $tier);
        $request->attributes->set('ai_max_tokens_per_request', $maxTokensPerRequest);
        $request->attributes->set('ai_max_tokens_per_session', $maxTokensPerSession);
        
        return $next($request);
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

