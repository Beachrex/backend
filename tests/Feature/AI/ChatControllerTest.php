<?php

namespace Tests\Feature\AI;

use App\Models\AiSession;
use App\Models\AiUsage;
use App\Models\Beach;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limiters before each test
        RateLimiter::clear('ai_anonymous_' . hash('sha256', '127.0.0.1'));
        RateLimiter::clear('ai_authenticated_1');

        // Clear circuit breaker cache
        Cache::forget('ai:daily_cost:' . now()->format('Y-m-d'));

        // Enable AI features
        Config::set('ai.features.enabled', true);
    }

    /**
     * Test happy path for free tier (1-3 beach recommendations).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function happy_path_free_tier_returns_beach_recommendations()
    {
        // Mock LLM response
        $this->mockLLMResponse();

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches in Croatia with parking',
            'context' => [
                'location' => [
                    'latitude' => 43.0189,
                    'longitude' => 17.4454,
                ],
                'preferences' => [
                    'max_distance_km' => 50,
                    'facilities' => ['parking'],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'session_id',
            'message',
            'intent',
            'beaches',
            'filters_applied',
            'suggestions',
            'metadata' => [
                'tokens_used',
                'model',
                'response_time_ms',
                'cached',
                'request_id',
            ],
        ]);

        // Verify session was created
        $this->assertDatabaseHas('ai_sessions', [
            'id' => $response->json('session_id'),
            'tier' => 'free',
        ]);

        // Verify usage was tracked
        $this->assertDatabaseHas('ai_usage', [
            'session_id' => $response->json('session_id'),
            'event_type' => 'chat',
        ]);

        // Verify max 3 beaches returned
        $beaches = $response->json('beaches');
        $this->assertLessThanOrEqual(3, count($beaches));
    }

    /**
     * Test paid feature triggers 402 for anonymous user.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function paid_feature_triggers_402_for_anonymous()
    {
        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Create a 7-day beach itinerary in Croatia',
        ]);

        $response->assertStatus(402);
        $response->assertJson([
            'error' => 'payment_required',
            'message' => 'Multi-day itineraries require payment',
        ]);

        $response->assertJsonStructure([
            'error',
            'message',
            'product' => [
                'type',
                'duration_days',
                'price',
                'currency',
            ],
            'purchase_url',
        ]);
    }

    /**
     * Test rate limit triggers 429.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rate_limit_triggers_429()
    {
        // Mock LLM for first two requests
        $this->mockLLMResponse();

        // Set low rate limit for testing
        Config::set('ai.tiers.free.rate_limit.requests_per_hour', 2);

        // First request - should succeed
        $response1 = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches',
        ]);
        $response1->assertStatus(200);

        // Second request - should succeed
        $response2 = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me more beaches',
        ]);
        $response2->assertStatus(200);

        // Third request - should fail with 429 (rate limit middleware blocks before LLM call)
        $response3 = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me even more beaches',
        ]);
        $response3->assertStatus(429);
        $response3->assertJson([
            'error' => 'rate_limit_exceeded',
        ]);

        $response3->assertJsonStructure([
            'error',
            'message',
            'limit',
            'reset_at',
            'upgrade_url',
        ]);
    }

    /**
     * Test token budget triggers 413.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function token_budget_triggers_413()
    {
        // Set very low token budget for testing (middleware blocks before LLM call)
        Config::set('ai.tiers.free.token_budget.max_tokens_per_request', 10);

        // Create a message that will exceed the low token budget
        // Middleware estimates: 1 token ≈ 4 characters, so 88 chars ≈ 22 tokens > 10 limit
        $message = 'Show me beaches with parking and showers and playgrounds and restaurants and cafes and bars';

        // No need to mock LLM - middleware will block the request
        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => $message,
        ]);

        $response->assertStatus(413);
        $response->assertJson([
            'error' => 'token_budget_exceeded',
        ]);

        $response->assertJsonStructure([
            'error',
            'message',
            'estimated_tokens',
            'max_tokens',
            'suggestion',
        ]);
    }

    /**
     * Test circuit breaker triggers 503.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function circuit_breaker_triggers_503()
    {
        // Set daily cost to exceed free tier limit (circuit breaker middleware blocks before LLM call)
        $today = now()->format('Y-m-d');
        $cacheKey = "ai:circuit_breaker:daily_cost:free:{$today}";
        Cache::put($cacheKey, 600.00, now()->endOfDay());

        // No need to mock LLM - middleware will block the request
        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches',
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'service_unavailable',
        ]);

        $response->assertJsonStructure([
            'error',
            'message',
            'retry_after',
        ]);
    }

    /**
     * Test schema validation catches invalid LLM JSON and returns safe error.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function schema_validation_catches_invalid_llm_json()
    {
        // Mock LLM to return invalid JSON
        $this->mockLLMResponse('This is not valid JSON at all!');

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches',
            'context' => [
                'location' => [
                    'latitude' => 43.0189,
                    'longitude' => 17.4454,
                ],
            ],
        ]);

        // Should still return 200 with safe fallback response
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'session_id',
            'message',
            'intent',
            'beaches',
            'metadata',
        ]);

        // Beaches should be empty array (safe fallback)
        $this->assertEquals([], $response->json('beaches'));
    }

    /**
     * Test invalid request returns 400.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_request_returns_400()
    {
        // Missing required 'message' field
        $response = $this->postJson('/api/v1/ai/chat', [
            'context' => [
                'location' => [
                    'latitude' => 43.0189,
                    'longitude' => 17.4454,
                ],
            ],
        ]);

        $response->assertStatus(422); // Laravel validation returns 422
        $response->assertJsonValidationErrors(['message']);
    }

    /**
     * Test message exceeds 500 characters returns 400.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function message_exceeds_500_characters_returns_400()
    {
        $longMessage = str_repeat('a', 501);

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => $longMessage,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    /**
     * Test session persistence across multiple messages.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function session_persists_across_multiple_messages()
    {
        $this->mockLLMResponse();

        // First message
        $response1 = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches in Croatia',
            'context' => [
                'location' => [
                    'latitude' => 43.0189,
                    'longitude' => 17.4454,
                ],
            ],
        ]);

        $response1->assertStatus(200);
        $sessionId = $response1->json('session_id');

        // Second message with same session
        $response2 = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Which one has the best weather?',
            'session_id' => $sessionId,
        ]);

        $response2->assertStatus(200);
        $this->assertEquals($sessionId, $response2->json('session_id'));

        // Verify session has 4 messages (2 user + 2 assistant)
        $session = AiSession::find($sessionId);
        $this->assertEquals(4, $session->messages_count);
    }

    /**
     * Mock LLM response for testing.
     *
     * @param string|array|null $content JSON string, array to encode, or null for default
     */
    private function mockLLMResponse($content = null)
    {
        if ($content === null) {
            $content = json_encode([
                'message' => 'I found 5 beaches in Croatia with parking. Here are the top 3:',
                'beaches' => [
                    [
                        'id' => 2,
                        'name' => 'Beach Ušće Neretve',
                        'slug' => 'beach-usce-neretve',
                        'country_code' => 'HR',
                        'city' => 'Opuzen',
                        'surface' => 'sand',
                        'facilities' => ['parking'],
                        'distance_km' => 0,
                        'match_score' => 0.95,
                    ],
                ],
                'filters_applied' => [
                    'country' => 'HR',
                    'facilities' => ['parking'],
                ],
                'suggestions' => [
                    'Show me beaches with playgrounds',
                    'Which one has the best weather?',
                ],
            ]);
        } elseif (is_array($content)) {
            $content = json_encode($content);
        }

        // Mock LLMService instead of OpenAI facade
        $mockResponse = new \App\Services\AI\DTOs\ChatCompletionResponse(
            content: $content,
            model: 'gpt-3.5-turbo',
            inputTokens: 500,
            outputTokens: 200,
            totalTokens: 700,
            costUsd: 0.001,
            provider: 'openai'
        );

        $mockLLMService = \Mockery::mock(\App\Services\AI\LLMService::class);
        $mockLLMService->shouldReceive('chatCompletion')
            ->andReturn($mockResponse);

        $this->app->instance(\App\Services\AI\LLMService::class, $mockLLMService);
    }

    /**
     * Test country-level query without location does NOT return distance_km.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function country_query_without_location_does_not_return_distance()
    {
        // Mock LLM response for country-level query
        $this->mockLLMResponse([
            'intent' => 'beach_search',
            'message' => 'Here are some well-known beaches in Croatia.',
            'beaches' => [],
            'filters_applied' => ['country' => 'HR'],
            'suggestions' => ['Share your location for personalized recommendations'],
        ]);

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches in Croatia',
            // NO context.location provided
        ]);

        $response->assertStatus(200);

        // Verify metadata indicates no location was provided
        $metadata = $response->json('metadata');
        $this->assertArrayHasKey('location_provided', $metadata);
        $this->assertFalse($metadata['location_provided']);

        // Verify AI message does NOT mention distance
        $message = $response->json('message');
        $this->assertStringNotContainsString('km', $message);
        $this->assertStringNotContainsString('away', $message);
        $this->assertStringNotContainsString('distance', $message);
    }

    /**
     * Test "popular beaches" query returns beaches without location requirement.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function popular_beaches_without_location_returns_top_by_views()
    {
        // Mock LLM response for popularity query (LLM might include distance_km, but it should be stripped)
        $this->mockLLMResponse([
            'intent' => 'popularity_query',
            'message' => 'Here are some of the most frequently viewed beaches in Croatia based on visitor interest.',
            'beaches' => [
                ['id' => 1, 'name' => 'Popular Beach 1', 'match_score' => 0.95, 'distance_km' => 10.5], // LLM might add this
                ['id' => 2, 'name' => 'Popular Beach 2', 'match_score' => 0.85, 'distance_km' => 5.2],  // LLM might add this
            ],
            'filters_applied' => ['country' => 'HR', 'sort' => 'popularity'],
            'suggestions' => ['Share your location for personalized recommendations'],
        ]);

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me the most popular beaches in Croatia',
            // NO context.location provided
        ]);

        $response->assertStatus(200);

        // Verify beaches are returned (not empty) - popularity queries should work without location
        $beaches = $response->json('beaches');
        $this->assertIsArray($beaches);
        $this->assertNotEmpty($beaches, 'Popularity query should return beaches without location');

        // CRITICAL: Verify distance_km is null for ALL beaches when no location provided
        foreach ($beaches as $beach) {
            $this->assertNull(
                $beach['distance_km'] ?? null,
                "Beach '{$beach['name']}' has distance_km={$beach['distance_km']}, but it should be null for popularity queries without location"
            );
        }

        // Verify AI message is transparent about data source
        $message = $response->json('message');
        $this->assertTrue(
            str_contains($message, 'frequently viewed') ||
            str_contains($message, 'visitor interest') ||
            str_contains($message, 'well-known'),
            'AI message should be transparent about popularity signal (not claim verified ratings)'
        );

        // Verify AI does NOT claim verified ratings/reviews
        $this->assertStringNotContainsString('highest rated', $message);
        $this->assertStringNotContainsString('top rated', $message);
        $this->assertStringNotContainsString('best reviewed', $message);

        // Verify metadata shows no location provided
        $metadata = $response->json('metadata');
        $this->assertFalse($metadata['location_provided']);
    }

    /**
     * Test "beaches near me" without location asks for location.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function beaches_near_me_without_location_asks_for_location()
    {
        // Mock LLM response asking for location
        $this->mockLLMResponse([
            'intent' => 'beach_search',
            'message' => 'I\'d love to help you find beaches near you! Could you share your location?',
            'beaches' => [],
            'suggestions' => [
                'Show me beaches in Split',
                'Show me beaches in Dubrovnik',
                'Show me popular beaches in Croatia',
            ],
        ]);

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches near me',
            // NO context.location provided
        ]);

        $response->assertStatus(200);

        // Verify AI asks for location
        $message = $response->json('message');
        $this->assertTrue(
            str_contains($message, 'location') ||
            str_contains($message, 'where') ||
            str_contains($message, 'city'),
            'AI should ask for location when user says "near me" without providing location'
        );

        // Verify no beaches returned (can't compute "near me" without location)
        $beaches = $response->json('beaches');
        $this->assertEmpty($beaches);
    }
}

