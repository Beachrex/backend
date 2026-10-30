<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\LLMProviderInterface;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\DTOs\ChatCompletionResponse;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\Providers\AnthropicProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Exceptions\LLMException;
use Illuminate\Support\Facades\Log;

/**
 * LLM Service
 * 
 * Facade for interacting with LLM providers.
 * Handles provider selection and provides a unified interface.
 * 
 * Source: docs/ai/architecture/mvp-integration.md Section 2.3
 */
class LLMService
{
    private LLMProviderInterface $provider;

    /** @var array<string, LLMProviderInterface> other providers, created when a request names one */
    private array $others = [];
    
    public function __construct()
    {
        $this->provider = $this->createProvider();
    }
    
    /**
     * Send a chat completion request.
     * 
     * @param ChatCompletionRequest $request
     * @return ChatCompletionResponse
     * @throws LLMException
     */
    public function chatCompletion(ChatCompletionRequest $request): ChatCompletionResponse
    {
        $startTime = microtime(true);
        $provider = $this->providerFor($request->provider);
        
        try {
            $response = $provider->chatCompletion($request);

            // Counted as soon as it is billed, so a later failure in the same request keeps this cost.
            AiSpendMeter::current()->add($response->provider ?? $provider->getProviderName(), (string) $response->model,
                $response->inputTokens, $response->outputTokens, $response->costUsd);
            
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            
            Log::info('LLM chat completion successful', [
                'provider' => $provider->getProviderName(),
                'model' => $response->model,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'total_tokens' => $response->totalTokens,
                'cost_usd' => $response->costUsd,
                'latency_ms' => $latencyMs,
            ]);
            
            return $response;
            
        } catch (LLMException $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            
            Log::error('LLM chat completion failed', [
                'provider' => $provider->getProviderName(),
                'error' => $e->getMessage(),
                'error_code' => $e->statusCode,
                'latency_ms' => $latencyMs,
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Create a simple chat completion with a single user message.
     * 
     * @param string $message User message
     * @param string|null $systemPrompt Optional system prompt
     * @param array $options Optional parameters (model, temperature, maxTokens, etc.)
     * @return ChatCompletionResponse
     * @throws LLMException
     */
    public function chat(string $message, ?string $systemPrompt = null, array $options = []): ChatCompletionResponse
    {
        $messages = [];
        
        if ($systemPrompt !== null) {
            $messages[] = ChatMessage::system($systemPrompt);
        }
        
        $messages[] = ChatMessage::user($message);
        
        $request = new ChatCompletionRequest(
            messages: $messages,
            model: $options['model'] ?? null,
            temperature: $options['temperature'] ?? null,
            maxTokens: $options['maxTokens'] ?? null,
            topP: $options['topP'] ?? null,
            n: $options['n'] ?? null,
            stop: $options['stop'] ?? null,
            user: $options['user'] ?? null,
        );
        
        return $this->chatCompletion($request);
    }
    
    /**
     * Count tokens in a text string.
     * 
     * @param string $text
     * @param string|null $model
     * @return int
     */
    public function countTokens(string $text, ?string $model = null): int
    {
        return $this->provider->countTokens($text, $model);
    }
    
    /**
     * Get the current provider.
     * 
     * @return LLMProviderInterface
     */
    public function getProvider(): LLMProviderInterface
    {
        return $this->provider;
    }
    
    /**
     * Get the provider name.
     * 
     * @return string
     */
    public function getProviderName(): string
    {
        return $this->provider->getProviderName();
    }
    
    /**
     * Get the default model.
     * 
     * @return string
     */
    public function getDefaultModel(): string
    {
        return $this->provider->getDefaultModel();
    }
    
    /**
     * Calculate cost for a completion.
     * 
     * @param string $model
     * @param int $inputTokens
     * @param int $outputTokens
     * @return float
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        return $this->provider->calculateCost($model, $inputTokens, $outputTokens);
    }
    
    /**
     * Create the appropriate provider based on configuration.
     * 
     * @return LLMProviderInterface
     * @throws \RuntimeException
     */
    private function createProvider(?string $providerName = null): LLMProviderInterface
    {
        $providerName ??= config('ai.provider', 'openai');
        
        return match ($providerName) {
            'openai' => new OpenAIProvider(),
            // Resolved from the container so tests can bind one with a fake HTTP client.
            'anthropic' => app(AnthropicProvider::class),
            default => throw new \RuntimeException("Unsupported LLM provider: {$providerName}"),
        };
    }

    /** The configured provider, or the one the request names (owner 2026-10-30: model picked in admin). */
    private function providerFor(?string $name): LLMProviderInterface
    {
        if ($name === null || $name === $this->provider->getProviderName()) {
            return $this->provider;
        }

        return $this->others[$name] ??= $this->createProvider($name);
    }
}

