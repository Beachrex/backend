<?php

namespace Tests\Feature\AI;

use Tests\TestCase;
use App\Services\AI\LLMService;
use App\Services\AI\DTOs\ChatMessage;
use App\Services\AI\DTOs\ChatCompletionRequest;
use App\Services\AI\Exceptions\LLMException;

/**
 * LLM Service Tests
 * 
 * Tests for LLM provider abstraction layer.
 * 
 * Note: These tests make real API calls to OpenAI and will incur costs.
 * Run sparingly or mock the OpenAI client for unit tests.
 */
class LLMServiceTest extends TestCase
{
    private LLMService $llm;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->llm = app(LLMService::class);
        
        // Skip tests if OpenAI API key is not configured
        if (empty(config('ai.providers.openai.api_key'))) {
            $this->markTestSkipped('OpenAI API key not configured');
        }
    }
    
    /**
     * Test: LLM service can be instantiated
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function llm_service_can_be_instantiated()
    {
        $this->assertInstanceOf(LLMService::class, $this->llm);
        $this->assertEquals('openai', $this->llm->getProviderName());
        $this->assertNotEmpty($this->llm->getDefaultModel());
    }
    
    /**
     * Test: Token counting works
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function token_counting_works()
    {
        $text = 'Hello, how are you today?';
        $tokens = $this->llm->countTokens($text);
        
        // Rough estimation: 1 token ≈ 4 characters
        // "Hello, how are you today?" = 27 characters ≈ 7 tokens
        $this->assertGreaterThan(0, $tokens);
        $this->assertLessThan(20, $tokens); // Should be around 7 tokens
    }
    
    /**
     * Test: Cost calculation works
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function cost_calculation_works()
    {
        // gpt-3.5-turbo: $0.50 per 1M input tokens, $1.50 per 1M output tokens
        $cost = $this->llm->calculateCost('gpt-3.5-turbo', 1000, 500);
        
        // Expected: (1000/1M * 0.50) + (500/1M * 1.50) = 0.0005 + 0.00075 = 0.00125
        $this->assertEquals(0.00125, $cost);
    }
    
    /**
     * Test: Simple chat completion works
     * 
     * WARNING: This test makes a real API call and incurs costs (~$0.0001)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function simple_chat_completion_works()
    {
        $response = $this->llm->chat(
            message: 'Say "Hello, World!" and nothing else.',
            systemPrompt: 'You are a helpful assistant.',
            options: [
                'temperature' => 0.1,
                'maxTokens' => 10,
            ]
        );
        
        $this->assertNotEmpty($response->getContent());
        $this->assertGreaterThan(0, $response->inputTokens);
        $this->assertGreaterThan(0, $response->outputTokens);
        $this->assertGreaterThan(0, $response->totalTokens);
        $this->assertGreaterThan(0, $response->costUsd);
        $this->assertEquals('openai', $response->provider);
        $this->assertNotEmpty($response->model);
    }
    
    /**
     * Test: Chat completion with ChatCompletionRequest works
     * 
     * WARNING: This test makes a real API call and incurs costs (~$0.0001)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function chat_completion_with_request_object_works()
    {
        $request = new ChatCompletionRequest(
            messages: [
                ChatMessage::system('You are a helpful assistant.'),
                ChatMessage::user('What is 2+2? Answer with just the number.'),
            ],
            temperature: 0.1,
            maxTokens: 5,
        );
        
        $response = $this->llm->chatCompletion($request);
        
        $this->assertNotEmpty($response->getContent());
        $this->assertStringContainsString('4', $response->getContent());
        $this->assertGreaterThan(0, $response->inputTokens);
        $this->assertGreaterThan(0, $response->outputTokens);
        $this->assertEquals('openai', $response->provider);
    }
    
    /**
     * Test: Token usage is tracked correctly
     * 
     * WARNING: This test makes a real API call and incurs costs (~$0.0001)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function token_usage_is_tracked_correctly()
    {
        $response = $this->llm->chat(
            message: 'Hi',
            systemPrompt: 'You are a helpful assistant.',
            options: ['maxTokens' => 10]
        );
        
        $usage = $response->getTokenUsage();
        
        $this->assertArrayHasKey('input', $usage);
        $this->assertArrayHasKey('output', $usage);
        $this->assertArrayHasKey('total', $usage);
        $this->assertEquals($usage['input'] + $usage['output'], $usage['total']);
    }
}

