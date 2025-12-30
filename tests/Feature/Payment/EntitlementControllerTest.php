<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use App\Models\Status;
use App\Models\Purchase;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementControllerTest extends TestCase
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
    }

    /**
     * Test 401 Unauthorized when unauthenticated.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_gets_401()
    {
        $response = $this->getJson('/api/v1/me/entitlements');

        $response->assertStatus(401);
    }

    /**
     * Test 200 OK with correct structure when authenticated.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_gets_entitlements()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/entitlements');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'entitlements' => [
                'itinerary_3day_credits',
                'itinerary_7day_credits',
                'itinerary_14day_credits',
            ],
            'purchases' => [
                '*' => [
                    'id',
                    'product_type',
                    'amount',
                    'currency',
                    'status',
                    'purchased_at',
                ],
            ],
        ]);

        // Verify default credits are 0
        $response->assertJson([
            'entitlements' => [
                'itinerary_3day_credits' => 0,
                'itinerary_7day_credits' => 0,
                'itinerary_14day_credits' => 0,
            ],
        ]);
    }

    /**
     * Test returns correct credit balances.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_correct_credit_balances()
    {
        $user = User::factory()->create();

        // Create entitlement with credits
        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'itinerary_3day_credits' => 2,
            'itinerary_7day_credits' => 1,
            'itinerary_14day_credits' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/entitlements');

        $response->assertStatus(200);
        $response->assertJson([
            'entitlements' => [
                'itinerary_3day_credits' => 2,
                'itinerary_7day_credits' => 1,
                'itinerary_14day_credits' => 0,
            ],
        ]);
    }

    /**
     * Test returns purchase history.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_purchase_history()
    {
        $user = User::factory()->create();

        // Create purchases
        Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'itinerary_3day',
            'amount' => 19.00,
            'currency' => 'USD',
            'status' => 'completed',
            'stripe_session_id' => 'cs_test_1',
            'stripe_payment_intent_id' => 'pi_test_1',
            'stripe_event_id' => 'evt_test_1',
            'purchased_at' => now()->subDays(5),
        ]);

        Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'itinerary_7day',
            'amount' => 39.00,
            'currency' => 'USD',
            'status' => 'completed',
            'stripe_session_id' => 'cs_test_2',
            'stripe_payment_intent_id' => 'pi_test_2',
            'stripe_event_id' => 'evt_test_2',
            'purchased_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/entitlements');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'purchases');
        $response->assertJsonFragment([
            'product_type' => 'itinerary_3day',
            'amount' => 19.00,
            'status' => 'completed',
        ]);
        $response->assertJsonFragment([
            'product_type' => 'itinerary_7day',
            'amount' => 39.00,
            'status' => 'completed',
        ]);
    }

    /**
     * Test returns negative credit balance.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_negative_credit_balance()
    {
        $user = User::factory()->create();

        // Create entitlement with negative credits (after refund)
        $entitlement = UserEntitlement::create([
            'user_id' => $user->id,
            'itinerary_3day_credits' => -1,
            'itinerary_7day_credits' => 0,
            'itinerary_14day_credits' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/entitlements');

        $response->assertStatus(200);
        $response->assertJson([
            'entitlements' => [
                'itinerary_3day_credits' => -1,
                'itinerary_7day_credits' => 0,
                'itinerary_14day_credits' => 0,
            ],
        ]);
    }

    /**
     * Test returns empty purchases array when no purchases.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_empty_purchases_when_none_exist()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/me/entitlements');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'purchases');
    }
}

