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

        // Create test beaches for context
        // The enrichBeaches() method requires beaches to exist in the context
        // to prevent LLM hallucination issues
        $this->createTestBeaches();
    }

    /**
     * Create test beaches with required relationships for context building.
     *
     * IMPORTANT: This method is called in setUp() for EVERY test.
     * RefreshDatabase clears the database between tests, so we need to recreate all data.
     */
    private function createTestBeaches(): void
    {
        // CRITICAL: Reset auto-increment sequences to ensure consistent IDs across tests
        // RefreshDatabase rolls back transactions, but doesn't reset sequences
        // Use pg_get_serial_sequence to get the correct sequence name
        \DB::statement("SELECT setval(pg_get_serial_sequence('beach', 'id'), 1, false)");
        \DB::statement("SELECT setval(pg_get_serial_sequence('region', 'id'), 1, false)");
        \DB::statement("SELECT setval(pg_get_serial_sequence('city', 'id'), 1, false)");

        // CRITICAL: Create Status FIRST (required by foreign keys in Region, City, Beach)
        // Use DB::table()->updateOrInsert() to handle RefreshDatabase clearing between tests
        \DB::table('status')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Active']
        );

        // Create required reference data
        $continent = \App\Models\Continent::updateOrCreate(
            ['code' => 'EU']
        );

        // Create continent translation (required by ContextBuilderService)
        \App\Models\ContinentTranslation::updateOrCreate(
            ['continent_code' => 'EU', 'locale' => 'en'],
            ['name' => 'Europe', 'slug' => 'europe']
        );

        $country = \App\Models\Country::updateOrCreate(
            ['code' => 'HR']
        );

        // Create country translation (required by ContextBuilderService)
        \App\Models\CountryTranslation::updateOrCreate(
            ['country_code' => 'HR', 'locale' => 'en'],
            ['name' => 'Croatia', 'slug' => 'croatia']
        );

        $region = \App\Models\Region::updateOrCreate(
            ['code' => 'TEST_REGION'],
            [
                'country_code' => 'HR',
                'status_id' => 1,
            ]
        );

        // Create region translation (required by ContextBuilderService)
        \App\Models\RegionTranslation::updateOrCreate(
            ['region_id' => $region->id, 'locale' => 'en'],
            ['name' => 'Test Region', 'slug' => 'test-region']
        );

        $city = \App\Models\City::updateOrCreate(
            ['code' => 'TEST_CITY'],
            [
                'country_code' => 'HR',
                'region_id' => $region->id,
                'status_id' => 1,
            ]
        );

        // Create city translation (required by ContextBuilderService)
        \App\Models\CityTranslation::updateOrCreate(
            ['city_id' => $city->id, 'locale' => 'en'],
            ['name' => 'Test City', 'slug' => 'test-city']
        );

        // Create test beaches with IDs 1 and 2 (used in test mocks)
        // Use updateOrCreate to handle RefreshDatabase
        Beach::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Zlatni Rat',
                'slug' => 'zlatni-rat',
                'status_id' => 1,
                'continent_code' => 'EU',
                'country_code' => 'HR',
                'region_id' => $region->id,
                'city_id' => $city->id,
                'latitude' => 43.2565,
                'longitude' => 16.6345,
                'beach_views' => 1000,
            ]
        );

        Beach::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Bačvice',
                'slug' => 'bacvice',
                'status_id' => 1,
                'continent_code' => 'EU',
                'country_code' => 'HR',
                'region_id' => $region->id,
                'city_id' => $city->id,
                'latitude' => 43.5051,
                'longitude' => 16.4402,
                'beach_views' => 800,
            ]
        );
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
            'message' => 'Multi-day itineraries require authentication and payment',
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
     * Test country-level query without location returns default beaches (not empty array).
     * MVP requirement: beach_search with country filter must return 1-3 beaches as suggestions.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function country_query_without_location_returns_default_beaches()
    {
        // Mock LLM response for country-level query with beaches
        // The LLM should receive beaches from context and include them in response
        $this->mockLLMResponse([
            'intent' => 'beach_search',
            'message' => 'Here are some popular beaches in Croatia based on visitor interest.',
            'beaches' => [
                ['id' => 1, 'name' => 'Zlatni Rat', 'match_score' => 0.95, 'distance_km' => null],
                ['id' => 2, 'name' => 'Bačvice', 'match_score' => 0.85, 'distance_km' => null],
            ],
            'filters_applied' => ['country' => 'HR'],
            'suggestions' => ['Share your location for personalized recommendations'],
        ]);

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'Show me beaches in Croatia',
            // NO context.location provided
        ]);

        $response->assertStatus(200);

        // CRITICAL: Beaches array must NOT be empty for country queries
        // This is the MVP requirement - beach_search with country must return suggestions
        $beaches = $response->json('beaches');
        $this->assertIsArray($beaches);
        $this->assertNotEmpty($beaches, 'beach_search with country filter must return beaches (not empty array)');
        $this->assertGreaterThanOrEqual(1, count($beaches), 'Should return at least 1 beach');
        $this->assertLessThanOrEqual(3, count($beaches), 'Should return at most 3 beaches');

        // Verify distance_km is null for all beaches (no location provided)
        foreach ($beaches as $beach) {
            $this->assertNull(
                $beach['distance_km'] ?? null,
                "Beach '{$beach['name']}' should have distance_km=null when no location provided"
            );
        }

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

    /**
     * Test filters_applied is null (not empty array) when no filters are applied.
     * This locks the behavior to match OpenAPI schema: filters_applied is type "object" nullable, not array.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function filters_applied_is_null_when_no_filters_used()
    {
        // Mock LLM response with NO filters applied
        $this->mockLLMResponse([
            'intent' => 'general_question',
            'message' => 'Beaches are coastal areas where land meets water, typically featuring sand, pebbles, or rocks.',
            'beaches' => [],
            'filters_applied' => null, // Explicitly null, not []
            'suggestions' => ['Search for beaches in Croatia', 'Find beaches near you'],
        ]);

        $response = $this->postJson('/api/v1/ai/chat', [
            'message' => 'What is a beach?',
        ]);

        $response->assertStatus(200);

        // CRITICAL: filters_applied must be null (not []) when no filters are applied
        // This matches OpenAPI schema: type="object", nullable=true
        $filtersApplied = $response->json('filters_applied');
        $this->assertNull(
            $filtersApplied,
            'filters_applied should be null (not empty array []) when no filters are applied. OpenAPI schema defines it as type="object" nullable=true.'
        );

        // Verify it's not an empty array
        $this->assertNotSame([], $filtersApplied, 'filters_applied should be null, not []');
    }
}

