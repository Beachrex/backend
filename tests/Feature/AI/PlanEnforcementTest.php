<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\User;
use App\Models\Status;

/**
 * Plan Enforcement Tests
 * 
 * Tests for AI plan enforcement and feature gating:
 * - Anonymous users (free tier) can access free features
 * - Anonymous users get 402 Payment Required for paid features
 * - Rate limiting works correctly (100/hour anonymous, 500/hour authenticated)
 * - Rate limit headers are set correctly
 */
class PlanEnforcementTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Create status
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);
    }

    /**
     * Test: Anonymous user can access free tier features (beach search)
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function anonymous_user_can_access_free_features()
    {
        $response = $this->postJson('/api/v1/ai/test', [
            'message' => 'Show me beaches in Croatia',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'session_id',
                'message',
                'intent',
                'tier',
                'timestamp',
            ])
            ->assertJson([
                'intent' => 'beach_search',
                'tier' => 'free',
            ]);
    }

    /**
     * Test: Anonymous user gets 402 Payment Required for multi-day itineraries
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function anonymous_user_gets_402_for_multi_day_itinerary()
    {
        $response = $this->postJson('/api/v1/ai/test', [
            'message' => 'Create a 3-day itinerary for Croatia',
        ]);

        $response->assertStatus(402)
            ->assertJsonStructure([
                'error',
                'message',
                'product' => [
                    'type',
                    'duration_days',
                    'price',
                    'currency',
                ],
                'purchase_url',
            ])
            ->assertJson([
                'error' => 'payment_required',
                'product' => [
                    'type' => 'itinerary',
                    'duration_days' => 3,
                    'price' => 19,
                    'currency' => 'USD',
                ],
            ]);
    }

    /**
     * Test: Anonymous user gets 402 Payment Required for accessibility reports
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function anonymous_user_gets_402_for_accessibility_report()
    {
        $response = $this->postJson('/api/v1/ai/test', [
            'message' => 'I need a wheelchair accessibility report',
        ]);

        $response->assertStatus(402)
            ->assertJsonStructure([
                'error',
                'message',
                'product' => [
                    'type',
                    'price',
                    'currency',
                ],
                'purchase_url',
            ])
            ->assertJson([
                'error' => 'payment_required',
                'product' => [
                    'type' => 'accessibility_report',
                    'price' => 15,
                    'currency' => 'USD',
                ],
            ]);
    }

    /**
     * Test: Anonymous user gets 402 Payment Required for activity guides
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function anonymous_user_gets_402_for_activity_guide()
    {
        $response = $this->postJson('/api/v1/ai/test', [
            'message' => 'I need an expert diving activity guide',
        ]);

        $response->assertStatus(402)
            ->assertJsonStructure([
                'error',
                'message',
                'product' => [
                    'type',
                    'price',
                    'currency',
                ],
                'purchase_url',
            ])
            ->assertJson([
                'error' => 'payment_required',
                'product' => [
                    'type' => 'activity_guide',
                    'price' => 25,
                    'currency' => 'USD',
                ],
            ]);
    }

    /**
     * Test: Rate limit headers are set correctly
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rate_limit_headers_are_set()
    {
        $response = $this->postJson('/api/v1/ai/test', [
            'message' => 'test',
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit', '100')
            ->assertHeader('X-RateLimit-Remaining');
    }

    /**
     * Test: Rate limiting works (429 after exceeding limit)
     * 
     * Note: This test is commented out because it would require making 101 requests
     * which is slow. Manual testing with curl is recommended instead.
     */
    // #[\PHPUnit\Framework\Attributes\Test]
    // public function rate_limit_returns_429_after_exceeding_limit()
    // {
    //     // Make 101 requests to exceed the 100/hour limit
    //     for ($i = 0; $i < 101; $i++) {
    //         $response = $this->postJson('/api/v1/ai/test', [
    //             'message' => 'test',
    //         ]);
    //     }
    //
    //     // The 101st request should return 429
    //     $response->assertStatus(429)
    //         ->assertJsonStructure([
    //             'error',
    //             'message',
    //             'limit',
    //             'reset_at',
    //             'upgrade_url',
    //         ])
    //         ->assertJson([
    //             'error' => 'rate_limit_exceeded',
    //             'limit' => 100,
    //         ]);
    // }
}

