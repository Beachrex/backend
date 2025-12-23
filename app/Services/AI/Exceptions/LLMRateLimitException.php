<?php

namespace App\Services\AI\Exceptions;

/**
 * LLM Rate Limit Exception
 * 
 * Thrown when the LLM provider rate limit is exceeded.
 */
class LLMRateLimitException extends LLMException
{
    public function __construct(
        string $message = 'LLM provider rate limit exceeded',
        ?string $provider = null,
        ?int $retryAfter = null,
        ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $provider,
            429,
            array_merge($context ?? [], ['retry_after' => $retryAfter]),
            $previous
        );
    }
}

