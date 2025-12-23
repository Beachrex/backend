<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;

/**
 * LLM Provider Interface
 * 
 * Abstract contract for LLM providers (OpenAI, Anthropic, etc.).
 * Ensures provider-agnostic implementation.
 * 
 * Source: docs/ai/architecture/mvp-integration.md Section 2.3
 */
interface LLMProviderInterface
{
    /**
     * Send a chat completion request to the LLM provider.
     * 
     * @param ChatCompletionRequest $request
     * @return ChatCompletionResponse
     * @throws \App\Services\AI\Exceptions\LLMException
     */
    public function chatCompletion(ChatCompletionRequest $request): ChatCompletionResponse;
    
    /**
     * Count tokens in a text string.
     * 
     * @param string $text
     * @param string|null $model Model name (optional, uses default if null)
     * @return int Token count
     */
    public function countTokens(string $text, ?string $model = null): int;
    
    /**
     * Get the provider name.
     * 
     * @return string Provider name ('openai', 'anthropic', etc.)
     */
    public function getProviderName(): string;
    
    /**
     * Get the default model for this provider.
     * 
     * @return string Model name
     */
    public function getDefaultModel(): string;
    
    /**
     * Calculate cost for a completion.
     * 
     * @param string $model Model name
     * @param int $inputTokens Input token count
     * @param int $outputTokens Output token count
     * @return float Cost in USD
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float;
}

