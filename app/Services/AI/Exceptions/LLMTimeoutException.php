<?php

namespace App\Services\AI\Exceptions;

/**
 * LLM Timeout Exception
 * 
 * Thrown when the LLM provider request times out.
 */
class LLMTimeoutException extends LLMException
{
    public function __construct(
        string $message = 'LLM provider request timed out',
        ?string $provider = null,
        ?int $timeout = null,
        ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $provider,
            504,
            array_merge($context ?? [], ['timeout' => $timeout]),
            $previous
        );
    }
}

