<?php

namespace App\Services\AI\DTOs;

/**
 * Chat Completion Response DTO
 * 
 * Represents a response from the LLM provider.
 */
class ChatCompletionResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $totalTokens,
        public readonly float $costUsd,
        public readonly string $provider,
        public readonly ?string $finishReason = null,
        public readonly ?array $rawResponse = null,
    ) {}
    
    /**
     * Get the assistant's message content.
     */
    public function getContent(): string
    {
        return $this->content;
    }
    
    /**
     * Get token usage summary.
     */
    public function getTokenUsage(): array
    {
        return [
            'input' => $this->inputTokens,
            'output' => $this->outputTokens,
            'total' => $this->totalTokens,
        ];
    }
    
    /**
     * Get cost in USD.
     */
    public function getCost(): float
    {
        return $this->costUsd;
    }
}

