<?php

namespace Tests\Feature\Payment;

use App\Models\Purchase;
use App\Models\Status;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\Payment\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * AI-3 (owner 2026-10-09): a payment grants credits exactly once and a refund revokes them exactly once,
 * whatever Stripe retries or repeats; unpaid sessions grant nothing; credits come from our product config.
 */
class WebhookSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Config::set('ai.payments_enabled', true);
        $this->user = User::factory()->create();

        $stripe = \Mockery::mock(StripeService::class);
        $stripe->shouldReceive('verifyWebhookSignature')->andReturnUsing(
            fn (string $payload) => \Stripe\Event::constructFrom(json_decode($payload, true)));
        $stripe->shouldReceive('getProductConfig')->andReturn(
            ['price_id' => 'price_x', 'amount' => 19.00, 'currency' => 'EUR', 'credits' => 1, 'name' => '3-Day']);
        $this->app->instance(StripeService::class, $stripe);
    }

    private function send(string $eventId, string $type, array $object): void
    {
        $this->call('POST', '/api/v1/payments/stripe/webhook', [], [], [],
            ['HTTP_STRIPE_SIGNATURE' => 'sig', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['id' => $eventId, 'type' => $type, 'data' => ['object' => $object]]))->assertOk();
    }

    private function checkoutSession(string $paymentStatus = 'paid', array $metadata = []): array
    {
        return ['id' => 'cs_1', 'payment_status' => $paymentStatus, 'payment_intent' => 'pi_1',
            'metadata' => ['user_id' => (string) $this->user->id, 'product_type' => 'itinerary_3day'] + $metadata];
    }

    private function credits(): int
    {
        return (int) UserEntitlement::where('user_id', $this->user->id)->value('itinerary_3day_credits');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_unpaid_session_grants_nothing_until_the_async_payment_succeeds(): void
    {
        $this->send('evt_1', 'checkout.session.completed', $this->checkoutSession('unpaid'));
        $this->assertSame(0, $this->credits());
        $this->assertSame(0, Purchase::count());

        $this->send('evt_2', 'checkout.session.async_payment_succeeded', $this->checkoutSession('paid'));
        $this->assertSame(1, $this->credits());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_same_payment_delivered_in_two_events_grants_once(): void
    {
        $this->send('evt_1', 'checkout.session.completed', $this->checkoutSession());
        $this->send('evt_2', 'checkout.session.async_payment_succeeded', $this->checkoutSession());

        $this->assertSame(1, $this->credits());
        $this->assertSame(1, Purchase::count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function credits_in_the_session_metadata_are_ignored(): void
    {
        $this->send('evt_1', 'checkout.session.completed', $this->checkoutSession('paid', ['credits' => '50']));

        $this->assertSame(1, $this->credits());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_refund_delivered_twice_revokes_once(): void
    {
        $this->send('evt_1', 'checkout.session.completed', $this->checkoutSession());
        $refund = ['id' => 'ch_1', 'payment_intent' => 'pi_1', 'refunded' => true, 'amount_refunded' => 1900];

        $this->send('evt_r1', 'charge.refunded', $refund);
        $this->send('evt_r2', 'charge.refunded', $refund);

        $this->assertSame(0, $this->credits());
        $this->assertSame(Purchase::STATUS_REFUNDED, Purchase::first()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_partial_refund_keeps_the_credits(): void
    {
        $this->send('evt_1', 'checkout.session.completed', $this->checkoutSession());
        $this->send('evt_r1', 'charge.refunded', ['id' => 'ch_1', 'payment_intent' => 'pi_1', 'refunded' => false, 'amount_refunded' => 500]);

        $this->assertSame(1, $this->credits());
        $this->assertSame(Purchase::STATUS_COMPLETED, Purchase::first()->status);
    }
}
