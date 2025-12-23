<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Structured Logging Service for AI Operations
 * 
 * Provides standardized, GDPR-compliant logging for all AI requests.
 * 
 * GDPR Compliance:
 * - No raw IP addresses (only SHA-256 hashes)
 * - No full user messages (only intent and metadata)
 * - No precise location data in logs (only in encrypted DB)
 * 
 * Log Context Schema:
 * - request_id: UUID for request tracing
 * - session_id: UUID for session tracking
 * - tier: User tier (free, basic, premium, enterprise)
 * - locale: User locale (en, hr, sl, etc.)
 * - latency_ms: Request latency in milliseconds
 * - tokens_input: Input tokens consumed
 * - tokens_output: Output tokens generated
 * - tokens_total: Total tokens consumed
 * - cost_usd: Cost in USD
 * - context_truncated: Whether context was truncated
 * - error_code: Error code if applicable
 * - http_status: HTTP status code
 * 
 * Source: docs/ai/implementation/STEP6_COMPLETION_REPORT.md
 */
class LoggingService
{
    /**
     * Log a successful AI request.
     * 
     * @param array $context Log context with standardized fields
     */
    public function logSuccess(array $context): void
    {
        $sanitized = $this->sanitizeContext($context);
        
        Log::info('AI request completed', array_merge($sanitized, [
            'http_status' => 200,
            'error_code' => null,
        ]));
    }
    
    /**
     * Log a failed AI request.
     * 
     * @param array $context Log context with standardized fields
     * @param int $httpStatus HTTP status code
     * @param string $errorCode Error code (e.g., 'rate_limit_exceeded')
     * @param string|null $errorMessage Optional error message
     */
    public function logError(array $context, int $httpStatus, string $errorCode, ?string $errorMessage = null): void
    {
        $sanitized = $this->sanitizeContext($context);
        
        Log::warning('AI request failed', array_merge($sanitized, [
            'http_status' => $httpStatus,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]));
    }
    
    /**
     * Log a circuit breaker trip.
     * 
     * @param string $tier Tier that triggered the circuit breaker
     * @param float $dailyCost Daily cost that triggered the circuit breaker
     * @param float $limit Circuit breaker limit
     */
    public function logCircuitBreakerTrip(string $tier, float $dailyCost, float $limit): void
    {
        Log::critical('AI circuit breaker triggered', [
            'tier' => $tier,
            'daily_cost_usd' => $dailyCost,
            'limit_usd' => $limit,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Log a rate limit hit.
     * 
     * @param string $tier User tier
     * @param string $identifier User identifier (hashed IP or user ID)
     * @param int $limit Rate limit
     * @param string $resetAt Reset timestamp
     */
    public function logRateLimitHit(string $tier, string $identifier, int $limit, string $resetAt): void
    {
        Log::warning('AI rate limit exceeded', [
            'tier' => $tier,
            'identifier_hash' => hash('sha256', $identifier), // Double-hash for extra privacy
            'limit' => $limit,
            'reset_at' => $resetAt,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Log a token budget exceeded event.
     * 
     * @param string $tier User tier
     * @param int $estimatedTokens Estimated tokens
     * @param int $limit Token limit
     * @param string $limitType Limit type ('per_request' or 'per_session')
     */
    public function logTokenBudgetExceeded(string $tier, int $estimatedTokens, int $limit, string $limitType): void
    {
        Log::warning('AI token budget exceeded', [
            'tier' => $tier,
            'estimated_tokens' => $estimatedTokens,
            'limit' => $limit,
            'limit_type' => $limitType,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Log a payment required event (402).
     * 
     * @param string $tier User tier
     * @param string $feature Feature that requires payment
     * @param float|null $price Price of the feature
     */
    public function logPaymentRequired(string $tier, string $feature, ?float $price = null): void
    {
        Log::info('AI payment required', [
            'tier' => $tier,
            'feature' => $feature,
            'price_usd' => $price,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Sanitize log context to ensure GDPR compliance.
     * 
     * Removes:
     * - Raw IP addresses
     * - Full user messages
     * - Precise location data
     * - Any PII
     * 
     * @param array $context Raw context
     * @return array Sanitized context
     */
    private function sanitizeContext(array $context): array
    {
        // Define allowed fields (whitelist approach)
        $allowedFields = [
            'request_id',
            'session_id',
            'tier',
            'locale',
            'latency_ms',
            'tokens_input',
            'tokens_output',
            'tokens_total',
            'cost_usd',
            'context_truncated',
            'beaches_fetched',
            'beaches_returned',
            'intent',
            'llm_provider',
            'llm_model',
            'cache_hit',
        ];
        
        // Filter context to only allowed fields
        $sanitized = array_intersect_key($context, array_flip($allowedFields));
        
        // Add timestamp
        $sanitized['timestamp'] = now()->toIso8601String();
        
        return $sanitized;
    }
    
    /**
     * Get log context from request and response data.
     * 
     * @param string $requestId Request ID
     * @param string $sessionId Session ID
     * @param string $tier User tier
     * @param string $locale User locale
     * @param int $latencyMs Request latency
     * @param object|null $llmResponse LLM response object
     * @param array|null $context Context data
     * @return array Standardized log context
     */
    public function buildContext(
        string $requestId,
        string $sessionId,
        string $tier,
        string $locale,
        int $latencyMs,
        ?object $llmResponse = null,
        ?array $context = null
    ): array {
        $logContext = [
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'tier' => $tier,
            'locale' => $locale,
            'latency_ms' => $latencyMs,
        ];
        
        if ($llmResponse) {
            $logContext['tokens_input'] = $llmResponse->inputTokens ?? 0;
            $logContext['tokens_output'] = $llmResponse->outputTokens ?? 0;
            $logContext['tokens_total'] = $llmResponse->totalTokens ?? 0;
            $logContext['cost_usd'] = $llmResponse->costUsd ?? 0.0;
            $logContext['llm_provider'] = $llmResponse->provider ?? 'unknown';
            $logContext['llm_model'] = $llmResponse->model ?? 'unknown';
        }
        
        if ($context) {
            $logContext['context_truncated'] = $context['metadata']['context_truncated'] ?? false;
            $logContext['beaches_fetched'] = $context['metadata']['beaches_fetched'] ?? 0;
            $logContext['beaches_returned'] = $context['metadata']['beaches_returned'] ?? 0;
        }
        
        return $logContext;
    }
}

