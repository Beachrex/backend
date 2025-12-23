<?php

namespace App\Services\AI\Exceptions;

use Exception;

/**
 * Base LLM Exception
 * 
 * Base exception for all LLM provider errors.
 */
class LLMException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $provider = null,
        public readonly ?int $statusCode = null,
        public readonly ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}

