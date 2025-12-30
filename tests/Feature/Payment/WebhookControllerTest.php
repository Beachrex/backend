<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use App\Models\Status;
use App\Models\Purchase;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
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

        // Enable payments and Stripe
        Config::set('ai.payments_enabled', true);
        Config::set('ai.stripe.enabled', true);
        Config::set('ai.stripe.secret_key', 'sk_test_fake_key');
        Config::set('ai.stripe.webhook_secret', 'whsec_test_secret');
    }

    /**
     * Test webhook rejects missing Stripe-Signature header.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_missing_stripe_signature()
    {
        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                ],
            ],
        ]);

        $response = $this->postJson('/api/v1/payments/stripe/webhook', [], [
            // Missing Stripe-Signature header
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test webhook rejects invalid Stripe-Signature.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rejects_invalid_stripe_signature()
    {
        $payload = json_encode([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                ],
            ],
        ]);

        $response = $this->withHeader('Stripe-Signature', 'invalid_signature')
            ->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true));

        $response->assertStatus(400);
    }

    /**
     * Test checkout.session.completed grants 1 credit.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function checkout_session_completed_grants_credit()
    {
        $user = User::factory()->create();

        // Create a valid Stripe event payload
        $payload = $this->createCheckoutSessionCompletedPayload($user->id, 'itinerary_3day');

        // Mock Stripe signature verification
        $this->mockStripeSignatureVerification($payload);

        $response = $this->withHeader('Stripe-Signature', 'valid_signature')
            ->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true));

        $response->assertStatus(200);

        // Assert purchase was created
        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'product_type' => 'itinerary_3day',
            'status' => 'completed',
            'stripe_session_id' => 'cs_test_123456',
            'stripe_event_id' => 'evt_test_123456',
        ]);

        // Assert credit was granted
        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $user->id,
            'itinerary_3day_credits' => 1,
        ]);
    }

    /**
     * Test idempotency: same stripe_event_id does not grant twice.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function idempotency_prevents_duplicate_credit_grant()
    {
        $user = User::factory()->create();

        // Create existing purchase with same stripe_event_id
        Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'itinerary_3day',
            'amount' => 19.00,
            'currency' => 'USD',
            'status' => 'completed',
            'stripe_session_id' => 'cs_test_123456',
            'stripe_payment_intent_id' => 'pi_test_123456',
            'stripe_event_id' => 'evt_test_123456',
            'purchased_at' => now(),
        ]);

        // Grant initial credit
        $entitlement = UserEntitlement::firstOrCreate(['user_id' => $user->id]);
        $entitlement->incrementCredits('itinerary_3day', 1);

        // Create same event payload
        $payload = $this->createCheckoutSessionCompletedPayload($user->id, 'itinerary_3day');

        // Mock Stripe signature verification
        $this->mockStripeSignatureVerification($payload);

        $response = $this->withHeader('Stripe-Signature', 'valid_signature')
            ->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true));

        $response->assertStatus(200);

        // Assert purchase count unchanged (still 1)
        $this->assertEquals(1, Purchase::where('user_id', $user->id)->count());

        // Assert credit balance unchanged (still 1)
        $entitlement->refresh();
        $this->assertEquals(1, $entitlement->itinerary_3day_credits);
    }

    /**
     * Test charge.refunded revokes credit.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function charge_refunded_revokes_credit()
    {
        $user = User::factory()->create();

        // Create existing purchase
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'itinerary_7day',
            'amount' => 39.00,
            'currency' => 'USD',
            'status' => 'completed',
            'stripe_session_id' => 'cs_test_123456',
            'stripe_payment_intent_id' => 'pi_test_123456',
            'stripe_event_id' => 'evt_test_original',
            'purchased_at' => now(),
        ]);

        // Grant credit
        $entitlement = UserEntitlement::firstOrCreate(['user_id' => $user->id]);
        $entitlement->incrementCredits('itinerary_7day', 1);

        // Create refund event payload
        $payload = $this->createChargeRefundedPayload('pi_test_123456');

        // Mock Stripe signature verification
        $this->mockStripeSignatureVerification($payload);

        $response = $this->withHeader('Stripe-Signature', 'valid_signature')
            ->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true));

        $response->assertStatus(200);

        // Assert purchase marked as refunded
        $purchase->refresh();
        $this->assertEquals('refunded', $purchase->status);

        // Assert credit was revoked (should be 0)
        $entitlement->refresh();
        $this->assertEquals(0, $entitlement->itinerary_7day_credits);
    }

    /**
     * Test charge.refunded can go negative if credit already consumed.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function charge_refunded_allows_negative_balance()
    {
        $user = User::factory()->create();

        // Create existing purchase
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'product_type' => 'itinerary_14day',
            'amount' => 59.00,
            'currency' => 'USD',
            'status' => 'completed',
            'stripe_session_id' => 'cs_test_123456',
            'stripe_payment_intent_id' => 'pi_test_123456',
            'stripe_event_id' => 'evt_test_original',
            'purchased_at' => now(),
        ]);

        // Grant credit and then consume it (simulate user already used the itinerary)
        $entitlement = UserEntitlement::firstOrCreate(['user_id' => $user->id]);
        $entitlement->incrementCredits('itinerary_14day', 1);
        $entitlement->decrementCredits('itinerary_14day', 1); // Now at 0

        // Create refund event payload
        $payload = $this->createChargeRefundedPayload('pi_test_123456');

        // Mock Stripe signature verification
        $this->mockStripeSignatureVerification($payload);

        $response = $this->withHeader('Stripe-Signature', 'valid_signature')
            ->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true));

        $response->assertStatus(200);

        // Assert purchase marked as refunded
        $purchase->refresh();
        $this->assertEquals('refunded', $purchase->status);

        // Assert credit went negative (0 - 1 = -1)
        $entitlement->refresh();
        $this->assertEquals(-1, $entitlement->itinerary_14day_credits);
    }

    /**
     * Helper: Create checkout.session.completed payload.
     */
    private function createCheckoutSessionCompletedPayload(int $userId, string $productType): string
    {
        return json_encode([
            'id' => 'evt_test_123456',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123456',
                    'payment_intent' => 'pi_test_123456',
                    'amount_total' => 1900, // $19.00 in cents
                    'currency' => 'usd',
                    'metadata' => [
                        'user_id' => (string) $userId,
                        'product_type' => $productType,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Helper: Create charge.refunded payload.
     */
    private function createChargeRefundedPayload(string $paymentIntentId): string
    {
        return json_encode([
            'id' => 'evt_test_refund_123',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_test_123456',
                    'payment_intent' => $paymentIntentId,
                    'amount_refunded' => 1900,
                    'refunded' => true,
                ],
            ],
        ]);
    }

    /**
     * Helper: Mock Stripe signature verification.
     */
    private function mockStripeSignatureVerification(string $payload): void
    {
        $eventData = json_decode($payload, true);

        // Use Stripe's constructFrom to create a proper Event object
        $event = \Stripe\Event::constructFrom($eventData);

        $mockStripeService = \Mockery::mock(\App\Services\Payment\StripeService::class);
        $mockStripeService->shouldReceive('verifyWebhookSignature')
            ->andReturn($event);

        // Mock getProductConfig for checkout.session.completed events
        if ($eventData['type'] === 'checkout.session.completed') {
            $productType = $eventData['data']['object']['metadata']['product_type'] ?? 'itinerary_3day';
            $mockStripeService->shouldReceive('getProductConfig')
                ->with($productType)
                ->andReturn([
                    'price_id' => 'price_test_' . $productType,
                    'amount' => 19.00,
                    'currency' => 'USD',
                    'credits' => 1,
                    'name' => '3-Day Beach Itinerary',
                ]);
        }

        // Mock getProductConfig for charge.refunded events (need to handle any product type)
        if ($eventData['type'] === 'charge.refunded') {
            $mockStripeService->shouldReceive('getProductConfig')
                ->andReturn([
                    'price_id' => 'price_test_itinerary_7day',
                    'amount' => 39.00,
                    'currency' => 'USD',
                    'credits' => 1,
                    'name' => '7-Day Beach Itinerary',
                ]);
        }

        $this->app->instance(\App\Services\Payment\StripeService::class, $mockStripeService);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

