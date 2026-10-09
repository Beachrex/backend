<?php

namespace Tests\Feature\Payment;

use App\Models\User;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CheckoutControllerTest extends TestCase
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
        Config::set('ai.stripe.products.itinerary_3day.price_id', 'price_test_3day');
        Config::set('ai.stripe.products.itinerary_7day.price_id', 'price_test_7day');
        Config::set('ai.stripe.products.itinerary_14day.price_id', 'price_test_14day');
    }

    /**
     * Test 401 Unauthorized when unauthenticated.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_gets_401()
    {
        $response = $this->postJson('/api/v1/payments/stripe/checkout', [
            'product_type' => 'itinerary_3day',
            'success_url' => 'https://beachrex.com/success',
            'cancel_url' => 'https://beachrex.com/cancel',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 200 OK when authenticated and Stripe enabled.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_create_checkout_session()
    {
        $user = User::factory()->create();

        // Create a proper Stripe Session object using constructFrom
        $mockSession = \Stripe\Checkout\Session::constructFrom([
            'id' => 'cs_test_123456',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123456',
        ]);

        $mockStripeService = \Mockery::mock(\App\Services\Payment\StripeService::class);
        $mockStripeService->shouldReceive('getProductConfig')
            ->with('itinerary_3day')
            ->andReturn([
                'price_id' => 'price_test_3day',
                'amount' => 19.00,
                'currency' => 'USD',
                'credits' => 1,
                'name' => '3-Day Beach Itinerary',
            ]);
        $mockStripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn ($u, $type, $success, $cancel, $consent) => $type === 'itinerary_3day'
                && str_ends_with($success, '/si/payment/success?session_id={CHECKOUT_SESSION_ID}')
                && str_ends_with($cancel, '/si/payment/cancel')
                && $consent['waive_withdrawal'] === true && ! empty($consent['accepted_at']))
            ->andReturn($mockSession);

        $this->app->instance(\App\Services\Payment\StripeService::class, $mockStripeService);

        $response = $this->actingAs($user)->withHeader('Accept-Language', 'sl')->postJson('/api/v1/payments/stripe/checkout', [
            'product_type' => 'itinerary_3day',
            'accept_terms' => true,
            'waive_withdrawal' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'checkout_url',
            'session_id',
            'product',
        ]);
        $response->assertJson([
            'session_id' => 'cs_test_123456',
            'checkout_url' => 'https://checkout.stripe.com/pay/cs_test_123456',
        ]);
    }

    /**
     * Test 503 Service Unavailable when payments disabled.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_503_when_payments_disabled()
    {
        Config::set('ai.payments_enabled', false);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/payments/stripe/checkout', [
            'product_type' => 'itinerary_3day',
        ]);

        $response->assertStatus(503);
        $response->assertJsonStructure([
            'error',
            'message',
        ]);
        $response->assertJson([
            'error' => 'payments_disabled',
        ]);
    }

    /**
     * Test 503 Service Unavailable when Stripe disabled.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_503_when_stripe_disabled()
    {
        Config::set('ai.stripe.enabled', false);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/payments/stripe/checkout', [
            'product_type' => 'itinerary_3day',
        ]);

        $response->assertStatus(503);
        $response->assertJsonStructure([
            'error',
            'message',
        ]);
        $response->assertJson([
            'error' => 'stripe_disabled',
        ]);
    }

    /**
     * Test 400 Bad Request for invalid product type.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_400_for_invalid_product_type()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/payments/stripe/checkout', [
            'product_type' => 'invalid_product',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'invalid_product',
        ]);
    }

    /**
     * Test 400 Bad Request for missing required fields.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_400_for_missing_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/payments/stripe/checkout', [
            // Missing all required fields
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'invalid_product',
        ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

