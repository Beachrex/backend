<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\LLMProviderInterface;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\Exceptions\LLMException;
use App\Services\AI\Exceptions\LLMRateLimitException;
use App\Services\AI\Exceptions\LLMQuotaExceededException;
use App\Services\AI\Exceptions\LLMTimeoutException;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Provider Implementation
 * 
 * Concrete implementation of LLMProviderInterface for OpenAI.
 * Uses the openai-php/laravel package.
 * 
 * Source: docs/ai/architecture/mvp-integration.md Section 2.3
 */
class OpenAIProvider implements LLMProviderInterface
{
    /**
     * OpenAI pricing per 1M tokens (as of 2024-12)
     * Source: https://openai.com/api/pricing/
     */
    private const PRICING = [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.150, 'output' => 0.600],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'gpt-4' => ['input' => 30.00, 'output' => 60.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
    ];
    
    private string $defaultModel;
    private int $timeout;
    
    public function __construct()
    {
        $this->defaultModel = config('ai.providers.openai.default_model', 'gpt-3.5-turbo');
        $this->timeout = config('ai.providers.openai.timeout', 30);
    }
    
    /**
     * {@inheritDoc}
     */
    public function chatCompletion(ChatCompletionRequest $request): ChatCompletionResponse
    {
        $model = $request->model ?? $this->defaultModel;
        
        try {
            $params = [
                'model' => $model,
                'messages' => $request->getMessagesArray(),
            ];
            
            // Add optional parameters
            if ($request->temperature !== null) {
                $params['temperature'] = $request->temperature;
            }
            if ($request->maxTokens !== null) {
                $params['max_tokens'] = $request->maxTokens;
            }
            if ($request->topP !== null) {
                $params['top_p'] = $request->topP;
            }
            if ($request->n !== null) {
                $params['n'] = $request->n;
            }
            if ($request->stop !== null) {
                $params['stop'] = $request->stop;
            }
            if ($request->user !== null) {
                $params['user'] = $request->user;
            }
            if ($request->responseFormat !== null) {
                $params['response_format'] = $request->responseFormat;
            }
            
            // Make API call
            $response = OpenAI::chat()->create($params);
            
            // Extract response data
            $content = $response->choices[0]->message->content ?? '';
            $finishReason = $response->choices[0]->finishReason ?? null;
            
            // Extract token usage
            $inputTokens = $response->usage->promptTokens ?? 0;
            $outputTokens = $response->usage->completionTokens ?? 0;
            $totalTokens = $response->usage->totalTokens ?? ($inputTokens + $outputTokens);
            
            // Calculate cost
            $cost = $this->calculateCost($model, $inputTokens, $outputTokens);
            
            return new ChatCompletionResponse(
                content: $content,
                model: $model,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                totalTokens: $totalTokens,
                costUsd: $cost,
                provider: 'openai',
                finishReason: $finishReason,
                rawResponse: $response->toArray(),
            );
            
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            $this->handleOpenAIError($e);
        } catch (\Exception $e) {
            Log::error('OpenAI API error', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            
            throw new LLMException(
                message: 'Failed to complete chat request: ' . $e->getMessage(),
                provider: 'openai',
                context: ['model' => $model],
                previous: $e
            );
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function countTokens(string $text, ?string $model = null): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        // For production, consider using tiktoken library for accurate counting
        // https://github.com/openai/tiktoken
        
        return (int) ceil(strlen($text) / 4);
    }
    
    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'openai';
    }
    
    /**
     * {@inheritDoc}
     */
    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }
    
    /**
     * {@inheritDoc}
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // Get pricing for model (fallback to gpt-3.5-turbo if not found)
        $pricing = self::PRICING[$model] ?? self::PRICING['gpt-3.5-turbo'];
        
        // Calculate cost per million tokens
        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];
        
        return round($inputCost + $outputCost, 6);
    }
    
    /**
     * Handle OpenAI-specific errors and map to our exceptions.
     */
    private function handleOpenAIError(\OpenAI\Exceptions\ErrorException $e): never
    {
        $errorMessage = $e->getMessage();
        $errorCode = $e->getCode();
        
        // Rate limit error (429)
        if ($errorCode === 429 || str_contains($errorMessage, 'rate_limit')) {
            throw new LLMRateLimitException(
                message: 'OpenAI rate limit exceeded: ' . $errorMessage,
                provider: 'openai',
                retryAfter: 60, // OpenAI typically uses 60 seconds
                previous: $e
            );
        }
        
        // Quota exceeded error (402 or insufficient_quota)
        if ($errorCode === 402 || str_contains($errorMessage, 'insufficient_quota') || str_contains($errorMessage, 'quota')) {
            throw new LLMQuotaExceededException(
                message: 'OpenAI quota exceeded: ' . $errorMessage,
                provider: 'openai',
                previous: $e
            );
        }
        
        // Timeout error
        if (str_contains($errorMessage, 'timeout') || str_contains($errorMessage, 'timed out')) {
            throw new LLMTimeoutException(
                message: 'OpenAI request timed out: ' . $errorMessage,
                provider: 'openai',
                timeout: $this->timeout,
                previous: $e
            );
        }
        
        // Generic error
        throw new LLMException(
            message: 'OpenAI API error: ' . $errorMessage,
            provider: 'openai',
            statusCode: $errorCode,
            previous: $e
        );
    }
}

