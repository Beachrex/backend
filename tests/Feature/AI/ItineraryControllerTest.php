<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ItineraryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create status records (required for User factory)
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);

        // Clear rate limiters before each test (use correct key format from middleware)
        // Note: We can't clear all keys, so we clear common test keys
        for ($i = 1; $i <= 10; $i++) {
            RateLimiter::clear('ai:rate_limit:user:' . $i);
        }

        // Clear circuit breaker cache (use correct key format from middleware)
        Cache::forget('ai:circuit_breaker:daily_cost:all:' . now()->format('Y-m-d'));
        Cache::forget('ai:circuit_breaker:daily_cost:free:' . now()->format('Y-m-d'));
        Cache::forget('ai:circuit_breaker:daily_cost:basic:' . now()->format('Y-m-d'));

        // Enable AI features
        Config::set('ai.features.enabled', true);
    }

    /**
     * Test 402 Payment Required for anonymous user (multi-day itineraries are PAID).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function anonymous_user_gets_402_payment_required()
    {
        $response = $this->postJson('/api/v1/ai/itineraries', [
            'destination' => [
                'country_code' => 'HR',
            ],
            'duration_days' => 7,
        ]);

        $response->assertStatus(402);
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

        // Verify product details
        $product = $response->json('product');
        $this->assertEquals('itinerary', $product['type']);
        $this->assertEquals(7, $product['duration_days']);
        $this->assertEquals(39.00, $product['price']); // 7-day = $39
        $this->assertEquals('USD', $product['currency']);
    }

    /**
     * Test 200 OK for authenticated user with paid tier.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_gets_itinerary()
    {
        // Create user with paid tier
        $user = User::factory()->create();

        // Mock PlanEnforcementService to return 'basic' tier (has multi_day_itineraries)
        $this->mockPlanEnforcementForBasicTier();

        // Mock LLM response
        $this->mockLLMResponse([
            'title' => '7-Day Beach Itinerary - Dalmatia, Croatia',
            'summary' => 'A balanced week around Split and nearby islands, mixing popular beaches with quieter coves.',
            'destination' => [
                'country_code' => 'HR',
                'region_slug' => null,
                'city_slug' => null,
            ],
            'duration_days' => 7,
            'days' => [
                [
                    'day_number' => 1,
                    'theme' => 'Split classics',
                    'base_area' => 'Split',
                    'items' => [
                        [
                            'type' => 'beach',
                            'time_block' => 'morning',
                            'beach' => [
                                'id' => 614,
                                'name' => 'Beach Bacvice',
                                'slug' => 'beach-bacvice',
                                'city' => 'Split',
                                'country_code' => 'HR',
                                'surface' => 'sand',
                                'facilities' => ['parking', 'showers'],
                                'distance_km' => null,
                            ],
                            'notes' => 'Arrive early for easier parking and calmer water.',
                            'why' => 'Good facilities and easy access for a first day.',
                        ],
                    ],
                    'logistics' => [
                        'estimated_travel_km' => 10,
                        'parking_notes' => 'Use nearby paid lots if street parking is full.',
                        'accessibility_notes' => null,
                    ],
                ],
            ],
            'assumptions' => [
                'Beach ordering uses visitor interest (beach_views) when no explicit location routing is provided.',
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/ai/itineraries', [
            'destination' => [
                'country_code' => 'HR',
            ],
            'duration_days' => 7,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'itinerary_id',
            'session_id',
            'title',
            'summary',
            'destination',
            'duration_days',
            'days' => [
                '*' => [
                    'day_number',
                    'theme',
                    'base_area',
                    'items' => [
                        '*' => [
                            'type',
                            'time_block',
                            'notes',
                            'why',
                        ],
                    ],
                    'logistics',
                ],
            ],
            'assumptions',
            'metadata' => [
                'request_id',
                'tier',
                'tokens_used',
                'model',
                'response_time_ms',
                'cached',
            ],
        ]);

        // Verify session was created
        $this->assertDatabaseHas('ai_sessions', [
            'id' => $response->json('session_id'),
            'user_id' => $user->id,
        ]);

        // Verify usage was tracked
        $this->assertDatabaseHas('ai_usage', [
            'session_id' => $response->json('session_id'),
            'event_type' => 'itinerary',
        ]);
    }

    /**
     * Test 422 Unprocessable Entity for invalid duration.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_duration_returns_422()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/ai/itineraries', [
            'destination' => [
                'country_code' => 'HR',
            ],
            'duration_days' => 5, // Invalid: must be 3, 7, or 14
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['duration_days']);
    }

    /**
     * Test 422 Unprocessable Entity for missing destination.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function missing_destination_returns_422()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/ai/itineraries', [
            'duration_days' => 7,
            // Missing destination
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['destination']);
    }

    /**
     * Test 429 Too Many Requests when rate limit exceeded.
     *
     * Note: Rate limit middleware runs before plan enforcement,
     * so we don't need to mock the plan enforcement service.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rate_limit_exceeded_returns_429()
    {
        $user = User::factory()->create();

        // Simulate rate limit exceeded (use correct key format from middleware)
        $key = 'ai:rate_limit:user:' . $user->id;
        for ($i = 0; $i < 500; $i++) {
            RateLimiter::hit($key, 3600);
        }

        $response = $this->actingAs($user)->postJson('/api/v1/ai/itineraries', [
            'destination' => [
                'country_code' => 'HR',
            ],
            'duration_days' => 7,
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure([
            'error',
            'message',
            'limit',
            'reset_at',
            'upgrade_url',
        ]);
    }

    /**
     * Test 503 Service Unavailable when circuit breaker triggered.
     *
     * Note: Circuit breaker middleware runs before plan enforcement,
     * so we don't need to mock the plan enforcement service.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function circuit_breaker_triggered_returns_503()
    {
        $user = User::factory()->create();

        // Trigger circuit breaker by exceeding daily cost limit (use correct key format from middleware)
        Cache::put('ai:circuit_breaker:daily_cost:all:' . now()->format('Y-m-d'), 5001, 86400);

        $response = $this->actingAs($user)->postJson('/api/v1/ai/itineraries', [
            'destination' => [
                'country_code' => 'HR',
            ],
            'duration_days' => 7,
        ]);

        $response->assertStatus(503);
        $response->assertJsonStructure([
            'error',
            'message',
            'retry_after',
        ]);
        $response->assertJson([
            'error' => 'service_unavailable',
        ]);
    }

    /**
     * Mock PlanEnforcementService to return 'basic' tier for authenticated users.
     */
    private function mockPlanEnforcementForBasicTier()
    {
        $mockService = \Mockery::mock(\App\Services\AI\PlanEnforcementService::class);
        $mockService->shouldReceive('determineTier')
            ->andReturn('basic');
        $mockService->shouldReceive('checkMultiDayItinerary')
            ->andReturn(null); // null means allowed

        $this->app->instance(\App\Services\AI\PlanEnforcementService::class, $mockService);
    }

    /**
     * Mock LLM response for testing.
     *
     * @param array|null $content Array to encode, or null for default
     */
    private function mockLLMResponse($content = null)
    {
        if ($content === null) {
            $content = [
                'title' => '3-Day Beach Itinerary - Croatia',
                'summary' => 'A short beach getaway in Croatia.',
                'destination' => [
                    'country_code' => 'HR',
                    'region_slug' => null,
                    'city_slug' => null,
                ],
                'duration_days' => 3,
                'days' => [
                    [
                        'day_number' => 1,
                        'theme' => 'Arrival day',
                        'base_area' => 'Split',
                        'items' => [
                            [
                                'type' => 'beach',
                                'time_block' => 'afternoon',
                                'beach' => [
                                    'id' => 1,
                                    'name' => 'Test Beach',
                                    'slug' => 'test-beach',
                                    'city' => 'Split',
                                    'country_code' => 'HR',
                                    'surface' => 'sand',
                                    'facilities' => [],
                                    'distance_km' => null,
                                ],
                                'notes' => 'Test notes',
                                'why' => 'Test reason',
                            ],
                        ],
                        'logistics' => [
                            'estimated_travel_km' => 5,
                            'parking_notes' => null,
                            'accessibility_notes' => null,
                        ],
                    ],
                ],
                'assumptions' => ['Test assumption'],
            ];
        }

        $content = json_encode($content);

        // Mock LLMService
        $mockResponse = new \App\Services\AI\DTOs\ChatCompletionResponse(
            content: $content,
            model: 'gpt-3.5-turbo',
            inputTokens: 1000,
            outputTokens: 500,
            totalTokens: 1500,
            costUsd: 0.002,
            provider: 'openai'
        );

        $mockLLMService = \Mockery::mock(\App\Services\AI\LLMService::class);
        $mockLLMService->shouldReceive('chatCompletion')
            ->andReturn($mockResponse);

        $this->app->instance(\App\Services\AI\LLMService::class, $mockLLMService);
    }
}

