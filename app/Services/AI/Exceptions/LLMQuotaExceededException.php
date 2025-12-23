<?php

namespace App\Services\AI\Exceptions;

/**
 * LLM Quota Exceeded Exception
 * 
 * Thrown when the LLM provider quota/billing limit is exceeded.
 */
class LLMQuotaExceededException extends LLMException
{
    public function __construct(
        string $message = 'LLM provider quota exceeded',
        ?string $provider = null,
        ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $provider,
            402,
            $context,
            $previous
        );
    }
}

